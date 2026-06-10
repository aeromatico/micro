<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\ContactConfig;
use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\Page;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;

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
        BackendMenu::setContext('Aero.Sites', 'mi-sitio', 'contenidos');
    }

    public function index()
    {
        $this->pageTitle = 'Contenidos';
        $tenant = $this->getCurrentTenant();

        $indexPage     = Page::forTenant($tenant->id)->where('slug', '')->first();
        $contactPage   = Page::forTenant($tenant->id)->where('slug', 'contacto')->first();
        $contactConfig = ContactConfig::where('tenant_id', $tenant->id)->first();

        $this->indexPageWidget    = $this->makePageFormWidget($indexPage,     'IndexPage',    'indexPageForm');
        $this->contactPageWidget  = $this->makePageFormWidget($contactPage,   'ContactPage',  'contactPageForm');
        $this->contactConfigWidget = $this->makeContactConfigWidget($contactConfig);

        $this->vars['indexPage']    = $indexPage;
        $this->vars['contactPage']  = $contactPage;
        $this->vars['submissions']  = ContactSubmission::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function index_onSaveIndex()
    {
        $tenant    = $this->getCurrentTenant();
        $indexPage = Page::forTenant($tenant->id)->where('slug', '')->firstOrFail();
        $data      = post('IndexPage', []);

        $indexPage->title   = $data['title']   ?? $indexPage->title;
        $indexPage->content = $data['content'] ?? '';
        $indexPage->save();

        Flash::success('Página de inicio guardada.');
        return [];
    }

    public function index_onSaveContactPage()
    {
        $tenant      = $this->getCurrentTenant();
        $contactPage = Page::forTenant($tenant->id)->where('slug', 'contacto')->firstOrFail();
        $data        = post('ContactPage', []);

        $contactPage->title   = $data['title']   ?? $contactPage->title;
        $contactPage->content = $data['content'] ?? '';
        $contactPage->save();

        Flash::success('Página de contacto guardada.');
        return [];
    }

    public function index_onSaveContactConfig()
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

    // -------------------------------------------------------------------------
    // Widget builders
    // -------------------------------------------------------------------------

    protected function makePageFormWidget(?Page $model, string $arrayName, string $alias): Form
    {
        $config            = new \stdClass;
        $config->model     = $model ?? new Page;
        $config->arrayName = $arrayName;
        $config->alias     = $alias;
        $config->fields    = [
            'title' => [
                'label'    => 'Título de la página',
                'type'     => 'text',
                'required' => true,
                'span'     => 'full',
            ],
            'content' => [
                'label' => 'Contenido',
                'type'  => 'richeditor',
                'size'  => 'huge',
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
