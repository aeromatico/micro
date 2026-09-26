<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Jobs\GenerateAiSiteJob;
use Aero\Sites\Models\AiGeneration;
use Aero\Sites\Models\Archetype;
use Aero\Sites\Models\DesignTheme;
use Aero\Sites\Models\Layout;
use Aero\Sites\Models\Page;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Traits\HasBrandingForm;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use ApplicationException;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use File;
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
    public ?Form $layoutWidget = null;

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

        // firstOrCreate y no firstOrNew: el form widget necesita un id real
        // para el update posterior en onSaveLayout, y así el resto del
        // request (partials de referencia, etc.) siempre encuentra la fila.
        $layout = Layout::firstOrCreate(['tenant_id' => $tenant->id], ['mode' => 'default']);
        $this->layoutWidget = $this->makeLayoutFormWidget($layout, 'Layout', 'layoutForm');

        $this->vars['indexPage']    = $indexPage;
        $this->vars['layout']       = $layout;
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
        // ComponentGallery, embebida aquí para no salir del editor de
        // contenidos (ver componentgallery/_gallery.php).
        $this->vars['blocks']             = ComponentGallery::BLOCKS;
        $this->vars['themes']             = DesignTheme::active()->orderBy('name')->get(['id', 'handle', 'name']);
        $this->vars['defaultThemeHandle'] = ComponentGallery::resolveDefaultThemeHandle($this->vars['themes']);

        // Pestaña "Plantilla" — fuente de referencia de lo que se sirve hoy
        // por defecto (Tailwind/Alpine propios, header/footer del theme),
        // para que el tenant sepa qué está reemplazando antes de tocar nada.
        // Theme fijo: todos los tenants corren sobre 'microsites' (ver
        // TenantProvisioner::createSiteDefinition).
        $themePath = themes_path('microsites');
        $this->vars['defaultBaseHtml']   = File::get($themePath . '/layouts/base.htm');
        $this->vars['defaultHeaderHtml'] = File::get($themePath . '/partials/site/header.htm');
        $this->vars['defaultFooterHtml'] = File::get($themePath . '/partials/site/footer.htm');
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function onSaveLayout()
    {
        $tenant = $this->getCurrentTenant();
        $layout = Layout::firstOrCreate(['tenant_id' => $tenant->id], ['mode' => 'default']);
        $data   = post('Layout', []);

        $layout->mode            = $data['mode'] ?? 'default';
        $layout->show_header     = !empty($data['show_header']);
        $layout->show_footer     = !empty($data['show_footer']);
        $layout->header_html     = $data['header_html'] ?? '';
        $layout->footer_html     = $data['footer_html'] ?? '';
        $layout->extra_head_html = $data['extra_head_html'] ?? '';
        $layout->custom_html     = $data['custom_html'] ?? '';
        $layout->save();

        Flash::success('Plantilla guardada.');
        return [];
    }

    /**
     * "Cargar navbar/footer actual" en la pestaña Plantilla — el admin ve un
     * textarea vacío y no le queda claro que ESO significa "ya está usando
     * el default", así que le ofrecemos traer ese default real (ya resuelto
     * con su nombre/logo/páginas, no el Twig fuente) como punto de partida
     * editable. Se resuelve pegándole a la home del propio tenant por HTTP
     * interno (mismo proceso, sin DNS/TLS de por medio) y recortando entre
     * los marcadores AERO:HEADER/FOOTER que dejan header.htm/footer.htm —
     * es la única forma de obtener el HTML ya evaluado (nombre, logo, lista
     * de páginas) sin reimplementar el Twig de esos partials en PHP.
     */
    public function onLoadDefaultHeader()
    {
        return ['html' => $this->fetchDefaultFragment('HEADER')];
    }

    public function onLoadDefaultFooter()
    {
        return ['html' => $this->fetchDefaultFragment('FOOTER')];
    }

    protected function fetchDefaultFragment(string $marker): string
    {
        $tenant = $this->getCurrentTenant();

        if ($tenant->isUnderConstruction()) {
            throw new ApplicationException('Tu sitio todavía está "en construcción" (sin landing generado ni contenido propio) — mientras tanto no se muestra navbar ni footer en ninguna página, así que no hay nada que traer todavía.');
        }

        try {
            $response = \Http::withHeaders(['Host' => $tenant->primary_domain])
                ->timeout(5)
                ->get('http://127.0.0.1/');
        } catch (\Throwable $e) {
            throw new ApplicationException('No se pudo cargar tu sitio para leer el ' . ($marker === 'HEADER' ? 'navbar' : 'footer') . ' actual. Intenta de nuevo.');
        }

        $body  = $response->body();
        $start = "<!-- AERO:{$marker}:START -->";
        $end   = "<!-- AERO:{$marker}:END -->";
        $startPos = strpos($body, $start);
        $endPos   = strpos($body, $end);

        if ($startPos === false || $endPos === false) {
            throw new ApplicationException('No se encontró un ' . ($marker === 'HEADER' ? 'navbar' : 'footer') . ' por defecto para traer — puede que ya estés usando uno propio en esta misma plantilla.');
        }

        return trim(substr($body, $startPos + strlen($start), $endPos - $startPos - strlen($start)));
    }

    public function onSaveIndex()
    {
        $tenant    = $this->getCurrentTenant();
        $indexPage = Page::forTenant($tenant->id)->where('slug', '')->first();

        // Guardar a mano sin haber generado nunca con IA: no hay fila de Page
        // todavía (TenantProvisioner no crea una) — se crea aquí igual que
        // hace GenerateAiSiteJob cuando termina una generación.
        if (!$indexPage) {
            $indexPage = new Page([
                'tenant_id'  => $tenant->id,
                'slug'       => '',
                'layout'     => 'home',
                'is_published' => true,
                'sort_order' => 1,
            ]);
        }

        $data      = post('IndexPage', []);

        $indexPage->title          = $data['title'] ?? $indexPage->title;
        $indexPage->content_mode   = $data['content_mode'] ?? $indexPage->content_mode ?? 'puck';
        $indexPage->is_placeholder = false;

        // Con plantilla propia (HTML completo) no hay editor visual ni IA
        // sobre el que aplicar bloques — el índice ya lo fuerza a "Código"
        // en el JS del tab Inicio, pero esto es la garantía real: si por lo
        // que sea llega otro valor en el post, igual se guarda como código.
        if ($tenant->layout && $tenant->layout->mode === 'custom' && $tenant->layout->custom_html) {
            $indexPage->content_mode = 'code';
        }

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

        $user = \BackendAuth::getUser();
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

        // OJO: NO usar la clave mágica '#ai-result' acá — el propio DomPatcher
        // de October terminaba pisando ese parche con la respuesta del primer
        // poll (carrera real, verificada con Playwright inspeccionando las
        // dos respuestas: la de onGenerateAi trae el patchDom correcto, pero
        // el contenido de #ai-result quedaba vacío igual). Se manda como dato
        // plano y el JS de _ai_panel_field.php lo inyecta a mano.
        return [
            'pendingHtml' => $this->makePartial('ai_pending', ['log_id' => $log->id]),
            'aiLogId'     => $log->id,
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

        // Mismo motivo que en onGenerateAi: claves de datos planas, no
        // '#ai-result' — el JS decide dónde y cómo pintarlo.
        if ($log->status === 'done') {
            Flash::success('¡Sitio generado con IA! Revisa la página de inicio.');
            $response['resultHtml'] = $this->makePartial('ai_preview', [
                'html' => $log->resultPage?->content ?? '',
            ]);
        } elseif ($log->status === 'failed') {
            $response['errorHtml'] = $this->makePartial('ai_error', [
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
            // "Tipo de contenido" va primero: es la elección que determina
            // qué más se ve debajo (panel de IA en modo Editor Visual,
            // campo de código en modo Código) — el admin no debería tener
            // que bajar hasta el fondo del formulario para encontrarla.
            'content_mode' => [
                'label'   => 'Tipo de contenido',
                'type'    => 'balloon-selector',
                'span'    => 'full',
                'default' => 'puck',
                'options' => [
                    'puck' => 'Editor Visual',
                    'code' => 'Código',
                ],
                'comment' => 'Editor Visual = bloques generados con IA o armados a mano. Código = pega tu propio HTML.',
            ],
            // Panel de generación con IA — campo tipo "partial" para que viva
            // DENTRO de este mismo Form widget, justo debajo de "Tipo de
            // contenido": el trigger show/hide de October solo encuentra el
            // campo disparador si ambos están en el mismo contenedor
            // data-control="formwidget" (ver controllers/contenteditor/
            // _ai_panel_field.php), así que no puede vivir fuera de $config
            // como HTML suelto en index.php — ahí quedaba fuera de sincro
            // con "Tipo de contenido" sin importar qué JS se le pusiera.
            'ai_panel' => [
                'type'    => 'partial',
                'path'    => 'ai_panel_field',
                'span'    => 'full',
                'trigger' => [
                    'action'    => 'show',
                    'field'     => 'content_mode',
                    'condition' => 'value[puck]',
                ],
            ],
            'title' => [
                'label'    => 'Título de la página',
                'type'     => 'text',
                'required' => true,
                'span'     => 'full',
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

    protected function makeLayoutFormWidget(Layout $layout, string $arrayName, string $alias): Form
    {
        $config            = new \stdClass;
        $config->model     = $layout;
        $config->arrayName = $arrayName;
        $config->alias     = $alias;
        $config->fields    = [
            'mode' => [
                'label'   => 'Modo',
                'type'    => 'balloon-selector',
                'span'    => 'full',
                'default' => 'default',
                'options' => [
                    'default' => 'Plataforma (con header/footer editables)',
                    'custom'  => 'Plantilla propia (HTML completo)',
                ],
                'comment' => 'Plataforma = usa las dependencias y el layout de siempre; puedes reemplazar solo el header/navbar y el footer. Plantilla propia = reemplazas TODO el documento (head, dependencias CDN propias, header/footer, scripts).',
            ],
            'show_header' => [
                'label'   => 'Mostrar header / navbar del layout',
                'type'    => 'switch',
                'span'    => 'left',
                'default' => true,
                'comment' => 'Apágalo si el header lo armas como bloque dentro de cada página.',
                'trigger' => ['action' => 'show', 'field' => 'mode', 'condition' => 'value[default]'],
            ],
            'show_footer' => [
                'label'   => 'Mostrar footer del layout',
                'type'    => 'switch',
                'span'    => 'right',
                'default' => true,
                'comment' => 'Apágalo si el footer lo armas como bloque dentro de cada página.',
                'trigger' => ['action' => 'show', 'field' => 'mode', 'condition' => 'value[default]'],
            ],
            'header_html' => [
                'label'     => 'Header / navbar propio',
                'type'      => 'codeeditor',
                'language'  => 'html',
                'size'      => 'large',
                'span'      => 'full',
                'comment'   => 'Vacío = usa el navbar por defecto del theme (ver pestaña "Referencia"). Si escribes algo aquí, reemplaza por completo ese navbar en todas las páginas.',
                'trigger'   => [
                    'action'    => 'show',
                    'field'     => 'mode',
                    'condition' => 'value[default]',
                ],
            ],
            'footer_html' => [
                'label'     => 'Footer propio',
                'type'      => 'codeeditor',
                'language'  => 'html',
                'size'      => 'large',
                'span'      => 'full',
                'comment'   => 'Vacío = usa el footer por defecto del theme (ver pestaña "Referencia").',
                'trigger'   => [
                    'action'    => 'show',
                    'field'     => 'mode',
                    'condition' => 'value[default]',
                ],
            ],
            'extra_head_html' => [
                'label'     => 'Assets adicionales (además de los nuestros)',
                'type'      => 'codeeditor',
                'language'  => 'html',
                'size'      => 'large',
                'span'      => 'full',
                'comment'   => 'Para cuando quieres seguir usando el stock de la plataforma (Tailwind/Alpine, header/footer) pero necesitas sumar UNA dependencia propia: una fuente, un <script src="..."> de terceros, un <link rel="stylesheet"> propio, etc. Se pega tal cual, justo antes de </head>, después de todo nuestro CSS/JS — así que puede sobreescribir estilos del theme si lo escribes a propósito. No reemplaza nada del stock (para eso existe "Plantilla propia").',
                'trigger'   => [
                    'action'    => 'show',
                    'field'     => 'mode',
                    'condition' => 'value[default]',
                ],
            ],
            'custom_html' => [
                'label'     => 'Documento HTML completo',
                'type'      => 'codeeditor',
                'language'  => 'html',
                'size'      => 'huge',
                'span'      => 'full',
                'comment'   => 'Pega tu documento completo (<html>, <head> con tus propias dependencias CDN, <body>). Marca dónde va el contenido de cada página con <!-- AERO:CONTENT --> (obligatorio) y dónde van los scripts del sitio (carrito, formulario de contacto) con <!-- AERO:SCRIPTS --> (si lo omites, esos formularios no van a funcionar).',
                'trigger'   => [
                    'action'    => 'show',
                    'field'     => 'mode',
                    'condition' => 'value[custom]',
                ],
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }
}
