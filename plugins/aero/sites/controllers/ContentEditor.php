<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Jobs\GenerateAiSiteJob;
use Aero\Sites\Models\AiGeneration;
use Aero\Sites\Models\Archetype;
use Aero\Sites\Models\ContactConfig;
use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\Page;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use ApplicationException;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;
use Input;
use Response;
use System\Models\File as FileModel;
use Validator;
use ValidationException;

class ContentEditor extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.sites.manage_pages'];

    public ?Form $indexPageWidget    = null;
    public ?Form $contactPageWidget  = null;
    public ?Form $contactConfigWidget = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sitio-web', 'contenidos');
    }

    public function index()
    {
        $this->pageTitle = 'Contenidos';
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            $this->vars['noTenant'] = true;
            $this->vars['indexPage'] = null;
            $this->vars['contactPage'] = null;
            $this->vars['submissions'] = collect();
            $this->vars['archetypes'] = collect();
            return;
        }

        $indexPage     = Page::forTenant($tenant->id)->where('slug', '')->first();
        $contactPage   = Page::forTenant($tenant->id)->where('slug', 'contacto')->first();
        $contactConfig = ContactConfig::where('tenant_id', $tenant->id)->first();

        $this->indexPageWidget     = $this->makePageFormWidget($indexPage,   'IndexPage',   'indexPageForm', $tenant);
        $this->contactPageWidget   = $this->makePageFormWidget($contactPage, 'ContactPage', 'contactPageForm', $tenant);
        $this->contactConfigWidget = $this->makeContactConfigWidget($contactConfig);

        $this->vars['indexPage']    = $indexPage;
        $this->vars['contactPage']  = $contactPage;
        $this->vars['submissions']  = ContactSubmission::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        $this->vars['archetypes']   = Archetype::active()
            ->forNiche($tenant->niche_type)
            ->orderBy('sort_order')
            ->get();
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function onSaveIndex()
    {
        $tenant    = $this->getCurrentTenant();
        $indexPage = Page::forTenant($tenant->id)->where('slug', '')->firstOrFail();
        $data      = post('IndexPage', []);

        $indexPage->title     = $data['title']   ?? $indexPage->title;
        $indexPage->puck_data = isset($data['puck_data']) ? json_decode($data['puck_data'], true) : $indexPage->puck_data;
        $indexPage->content   = $data['content'] ?? $indexPage->content;
        $indexPage->save();

        Flash::success('Página de inicio guardada.');
        return [];
    }

    public function onSaveContactPage()
    {
        $tenant      = $this->getCurrentTenant();
        $contactPage = Page::forTenant($tenant->id)->where('slug', 'contacto')->firstOrFail();
        $data        = post('ContactPage', []);

        $contactPage->title     = $data['title']   ?? $contactPage->title;
        $contactPage->puck_data = isset($data['puck_data']) ? json_decode($data['puck_data'], true) : $contactPage->puck_data;
        $contactPage->content   = $data['content'] ?? $contactPage->content;
        $contactPage->save();

        Flash::success('Página de contacto guardada.');
        return [];
    }

    public function onSaveContactConfig()
    {
        $tenant        = $this->getCurrentTenant();
        $contactConfig = ContactConfig::where('tenant_id', $tenant->id)->firstOrFail();
        $data          = post('ContactConfig', []);

        $contactConfig->form_enabled    = (bool) ($data['form_enabled'] ?? false);
        $contactConfig->success_message = $data['success_message'] ?? $contactConfig->success_message;
        $contactConfig->save();

        Flash::success('Configuración del formulario guardada.');
        return [];
    }

    /**
     * Sube una imagen desde el editor Puck (Hero/ImageBlock/Gallery/LogoCloud).
     * No usa la Media Library de October (storage/app/media) porque es una
     * biblioteca única y global — cualquier backend user con permiso
     * media.library navega los archivos de TODOS los tenants. Cada archivo
     * se crea como su propio System\Models\File adjunto solo a este tenant
     * (Tenant::$attachMany.puck_uploads), igual que logo/favicon.
     */
    public function onPuckUploadImage()
    {
        try {
            $tenant = $this->getCurrentTenant();
            if (!$tenant) {
                throw new ApplicationException('No se encontró el tenant actual.');
            }

            if (!Input::hasFile('file_data')) {
                throw new ApplicationException('No se recibió ningún archivo.');
            }

            $uploadedFile = files('file_data');

            $validation = Validator::make(
                ['file_data' => $uploadedFile],
                ['file_data' => ['max:10240', 'mimes:jpg,jpeg,png,gif,webp,svg']]
            );
            if ($validation->fails()) {
                throw new ValidationException($validation);
            }

            if (!$uploadedFile->isValid()) {
                throw new ApplicationException('El archivo no es válido.');
            }

            $file = new FileModel();
            $file->data = $uploadedFile;
            $file->is_public = true;
            $file->save();

            $tenant->puck_uploads()->add($file);

            return Response::make(['id' => $file->id, 'url' => $file->getPath()], 200);
        } catch (\Exception $ex) {
            return Response::make(['error' => $ex->getMessage()], 400);
        }
    }

    // -------------------------------------------------------------------------
    // AI Generation
    // -------------------------------------------------------------------------

    public function onGenerateAi()
    {
        $tenant = $this->getCurrentTenant();
        $prompt = post('ai_prompt', '');

        if (!$prompt || mb_strlen(trim($prompt)) < 10) {
            throw new \ApplicationException('Describe tu negocio con al menos 10 caracteres.');
        }

        // Restricted access: only demo tenant handle or superuser
        $user = \BackendAuth::getUser();
        if ($tenant->handle !== 'demo' && (!$user || !$user->is_superuser)) {
            throw new \ApplicationException('La generación con IA está habilitada solo para el tenant demo y superadministradores.');
        }

        // Check AiFields is configured
        try {
            if (!\Aero\AiFields\Models\Settings::isConfigured()) {
                throw new \ApplicationException('No hay un proveedor de IA configurado. Ve a Sistema → AI Fields y configura el endpoint, API key y modelo.');
            }
        } catch (\Exception $e) {
            if ($e instanceof \ApplicationException) throw $e;
        }

        $archetypeHandle = post('archetype_handle') ?: null;

        $log = AiGeneration::create([
            'tenant_id'        => $tenant->id,
            'user_id'          => $user?->id,
            'prompt'           => mb_substr($prompt, 0, 2000),
            'status'           => 'pending',
            'archetype_handle' => $archetypeHandle,
        ]);

        GenerateAiSiteJob::dispatch($tenant->id, $prompt, $log->id, $archetypeHandle);

        return [
            '#ai-result' => $this->makePartial('ai_pending', ['log_id' => $log->id]),
            'aiLogId'    => $log->id,
        ];
    }

    public function onCheckAiStatus()
    {
        $tenant = $this->getCurrentTenant();
        $logId  = post('log_id');

        $log = AiGeneration::where('id', $logId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$log) {
            throw new \ApplicationException('Generación no encontrada.');
        }

        $response = ['status' => $log->status];

        if ($log->status === 'done') {
            Flash::success('¡Sitio generado con IA! Revisá la página de inicio.');
            $response['#ai-result'] = $this->makePartial('ai_preview', [
                'html' => $log->resultPage?->content ?? '',
            ]);
        } elseif ($log->status === 'failed') {
            $response['#ai-result'] = $this->makePartial('ai_error', [
                'message' => $log->error_message ?: 'La IA no pudo generar contenido válido después de varios intentos.',
            ]);
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Widget builders
    // -------------------------------------------------------------------------

    protected function makePageFormWidget(?Page $model, string $arrayName, string $alias, Tenant $tenant): Form
    {
        $model ??= new Page;
        // Páginas nuevas (aún no guardadas) no tienen tenant_id seteado — sin
        // esto, PuckEditor::prepareVars() no podría resolver la paleta/fuentes
        // del tenant para inyectar sus CSS vars en el editor visual.
        $model->setRelation('tenant', $tenant);

        $config            = new \stdClass;
        $config->model     = $model;
        $config->arrayName = $arrayName;
        $config->alias     = $alias;
        $config->fields    = [
            'title' => [
                'label'    => 'Título de la página',
                'type'     => 'text',
                'required' => true,
                'span'     => 'full',
            ],
            'puck_data' => [
                'label' => 'Editor Visual',
                'type'  => 'puckEditor',
                'span'  => 'full',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }

    protected function makeContactConfigWidget(?ContactConfig $model): Form
    {
        $config            = new \stdClass;
        $config->model     = $model ?? new ContactConfig;
        $config->arrayName = 'ContactConfig';
        $config->alias     = 'contactConfigForm';
        $config->fields    = [
            'form_enabled' => [
                'label'   => 'Formulario de contacto activo',
                'type'    => 'checkbox',
                'default' => true,
                'span'    => 'left',
            ],
            'success_message' => [
                'label'       => 'Mensaje de éxito',
                'type'        => 'text',
                'span'        => 'full',
                'placeholder' => '¡Gracias! Nos comunicaremos contigo pronto.',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }
}
