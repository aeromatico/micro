<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Jobs\GenerateAiSiteJob;
use Aero\Sites\Models\AiGeneration;
use Aero\Sites\Models\Archetype;
use Aero\Sites\Models\DesignTheme;
use Aero\Sites\Models\Page;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Traits\HasBrandingForm;
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
    use ResolvesCurrentTenant, HasBrandingForm;

    public $requiredPermissions = ['aero.sites.manage_pages'];

    public ?Form $indexPageWidget = null;

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
            $this->vars['archetypes'] = collect();
            return;
        }

        $indexPage = Page::forTenant($tenant->id)->where('slug', '')->first();

        $this->indexPageWidget = $this->makePageFormWidget($indexPage, 'IndexPage', 'indexPageForm', $tenant);
        $this->brandingWidget  = $this->makeBrandingWidget($tenant);

        $this->vars['indexPage']    = $indexPage;
        $this->vars['tenant']       = $tenant;
        $this->vars['paletteVars']  = $tenant->getEffectiveCssVars();
        $this->vars['archetypes']   = Archetype::active()
            ->forNiche($tenant->niche_type)
            ->orderBy('sort_order')
            ->get();
        $this->vars['aiConnectors'] = $this->getAiConnectors();

        // Para prellenar el panel de "Rehacer con IA" con lo último que se usó
        // (el botón de acceso rápido regenera de un clic, sin que el usuario
        // tenga que volver a escribir el mismo prompt).
        $this->vars['lastGeneration'] = AiGeneration::where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->first();

        // Pestaña "Componentes" — misma galería de referencia de
        // ComponentGallery, embebida acá para no salir del editor de
        // contenidos (ver componentgallery/_gallery.php).
        $this->vars['blocks']             = ComponentGallery::BLOCKS;
        $this->vars['themes']             = DesignTheme::active()->orderBy('name')->get(['id', 'handle', 'name']);
        $this->vars['defaultThemeHandle'] = ComponentGallery::resolveDefaultThemeHandle($this->vars['themes']);
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function onSaveIndex()
    {
        $tenant    = $this->getCurrentTenant();
        $indexPage = Page::forTenant($tenant->id)->where('slug', '')->firstOrFail();
        $data      = post('IndexPage', []);

        $indexPage->title        = $data['title'] ?? $indexPage->title;
        $indexPage->content_mode = $data['content_mode'] ?? $indexPage->content_mode ?? 'puck';

        if ($indexPage->content_mode === 'code') {
            $indexPage->content = $data['content_raw'] ?? '';
        } else {
            $indexPage->puck_data = isset($data['puck_data']) ? json_decode($data['puck_data'], true) : $indexPage->puck_data;
            $indexPage->content   = $data['content'] ?? $indexPage->content;
        }

        $indexPage->save();

        Flash::success('Página de inicio guardada.');
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

        $connectorId = (int) post('connector_id') ?: null;

        if ($connectorId) {
            // El usuario eligió un modelo puntual (Aero.Connector) — no
            // depende de que Aero.AiFields esté configurado, pero sí de que
            // ese connector siga existiendo/habilitado.
            $connector = class_exists(\Aero\Connector\Models\Connector::class)
                ? \Aero\Connector\Models\Connector::find($connectorId)
                : null;
            if (!$connector || !$connector->is_enabled) {
                throw new \ApplicationException('El modelo de IA elegido ya no está disponible.');
            }
        } else {
            // Sin connector elegido: usa el proveedor único de Aero.AiFields (default de siempre).
            try {
                if (!\Aero\AiFields\Models\Settings::isConfigured()) {
                    throw new \ApplicationException('No hay un proveedor de IA configurado. Ve a Sistema → AI Fields y configura el endpoint, API key y modelo.');
                }
            } catch (\Exception $e) {
                if ($e instanceof \ApplicationException) throw $e;
            }
        }

        $archetypeHandle = post('archetype_handle') ?: null;

        $log = AiGeneration::create([
            'tenant_id'        => $tenant->id,
            'user_id'          => $user?->id,
            'prompt'           => mb_substr($prompt, 0, 2000),
            'status'           => 'pending',
            'archetype_handle' => $archetypeHandle,
            'connector_id'     => $connectorId,
        ]);

        GenerateAiSiteJob::dispatch($tenant->id, $prompt, $log->id, $archetypeHandle, $connectorId);

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
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Modelos de IA seleccionables en el panel de generación — cualquier
     * Connector habilitado de categoría "ai" (mismo criterio que
     * Aero\Chatbots\Models\Bot::getAiConnectorIdOptions()). Vacío si
     * Aero.Connector no está instalado o no hay ninguno configurado, en
     * cuyo caso el panel cae al proveedor único de Aero.AiFields.
     */
    protected function getAiConnectors()
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return collect();
        }

        return \Aero\Connector\Models\Connector::where('is_enabled', true)
            ->get()
            ->filter(fn ($c) => (\Aero\Connector\Classes\TypeRegistry::find($c->type)['category'] ?? null) === 'ai')
            ->values();
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
            'content_mode' => [
                'label'   => 'Tipo de contenido',
                'type'    => 'balloon-selector',
                'span'    => 'full',
                'default' => 'puck',
                'options' => [
                    'puck' => 'Editor Visual',
                    'code' => 'Código',
                ],
                'comment' => 'Editor Visual = bloques generados con IA o armados a mano. Código = pegá tu propio HTML.',
            ],
            'puck_data' => [
                'label'   => 'Editor Visual',
                'type'    => 'puckEditor',
                'span'    => 'full',
                'trigger' => [
                    'action'    => 'show',
                    'field'     => 'content_mode',
                    'condition' => 'value[puck]',
                ],
            ],
            'content_raw' => [
                'label'     => 'Código',
                'type'      => 'codeeditor',
                'language'  => 'html',
                'size'      => 'huge',
                'span'      => 'full',
                'valueFrom' => 'content',
                'comment'   => 'HTML propio. Se guarda y se muestra tal cual en la página de inicio.',
                'trigger'   => [
                    'action'    => 'show',
                    'field'     => 'content_mode',
                    'condition' => 'value[code]',
                ],
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }
}
