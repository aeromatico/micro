<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\ContactConfig;
use Aero\Sites\Models\NotificationChannel;
use Aero\Sites\Models\SeoConfig;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;

class SiteSettings extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.sites.manage_seo'];

    public ?Form $brandingWidget     = null;
    public ?Form $contactInfoWidget  = null;
    public ?Form $seoWidget          = null;
    public ?Form $channelFormWidget  = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'mi-sitio', 'configuracion');
    }

    public function index()
    {
        $this->pageTitle = 'Configuración del sitio';
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            $this->vars['noTenant'] = true;
            return;
        }

        $contactConfig = ContactConfig::where('tenant_id', $tenant->id)->first();
        $seoConfig     = SeoConfig::where('tenant_id', $tenant->id)->first();

        $this->brandingWidget    = $this->makeBrandingWidget($tenant);
        $this->contactInfoWidget = $this->makeContactInfoWidget($contactConfig);
        $this->seoWidget         = $this->makeSeoWidget($seoConfig);
        $this->channelFormWidget = $this->makeChannelFormWidget(new NotificationChannel);

        $this->vars['tenant']        = $tenant;
        $this->vars['contactConfig'] = $contactConfig;
        $this->vars['seoConfig']     = $seoConfig;
        $this->vars['channels']      = $this->getChannels($tenant->id);
        $this->vars['paletteVars']   = $tenant->getEffectiveCssVars();
    }

    // -------------------------------------------------------------------------
    // AJAX — Branding
    // -------------------------------------------------------------------------

    public function onSaveBranding()
    {
        $tenant = $this->getCurrentTenant();
        $data   = post('Branding', []);

        $tenant->name            = $data['name']            ?? $tenant->name;
        $tenant->primary_color   = $data['primary_color']   ?? $tenant->primary_color;
        $tenant->design_theme_id = $data['design_theme_id'] ?: null;

        $overridePrimary = trim((string) ($data['override_primary'] ?? ''));
        $overrideAccent  = trim((string) ($data['override_accent'] ?? ''));
        $colorOverrides  = array_filter([
            'primary' => $overridePrimary ?: null,
            'accent'  => $overrideAccent ?: null,
        ]);

        $overrideFontHeading  = trim((string) ($data['override_font_heading'] ?? ''));
        $overrideFontHeading2 = trim((string) ($data['override_font_heading2'] ?? ''));
        $overrideFontBody     = trim((string) ($data['override_font_body'] ?? ''));
        $fontOverrides = array_filter([
            'heading'  => $overrideFontHeading ?: null,
            'heading2' => $overrideFontHeading2 ?: null,
            'body'     => $overrideFontBody ?: null,
        ]);

        $overrides = [];
        if ($colorOverrides) $overrides['colors'] = $colorOverrides;
        if ($fontOverrides) $overrides['fonts'] = $fontOverrides;
        $tenant->theme_overrides = $overrides ?: null;

        // makeBrandingWidget() (ejecutado por index() antes de este handler AJAX,
        // sobre la misma instancia cacheada por ResolvesCurrentTenant) setea estos
        // atributos virtuales para precargar el form — no son columnas reales,
        // hay que descartarlos antes de save() o Eloquent intenta persistirlos.
        unset(
            $tenant->override_primary, $tenant->override_accent,
            $tenant->override_font_heading, $tenant->override_font_heading2, $tenant->override_font_body
        );

        $tenant->save();

        // Commit deferred file bindings (logo, favicon)
        $sessionKey = post('_session_key', '');
        if ($sessionKey) {
            $tenant->commitDeferred($sessionKey);
        }

        Flash::success('Branding guardado correctamente.');
        return [];
    }

    public function onSaveContactInfo()
    {
        $tenant        = $this->getCurrentTenant();
        $contactConfig = ContactConfig::where('tenant_id', $tenant->id)->firstOrFail();
        $data          = post('ContactInfo', []);

        $contactConfig->fill([
            'contact_email' => $data['contact_email'] ?: null,
            'phone'         => $data['phone']         ?: null,
            'whatsapp'      => $data['whatsapp']      ?: null,
            'address'       => $data['address']       ?: null,
            'lat'           => is_numeric($data['lat'] ?? '') ? (float) $data['lat'] : null,
            'lng'           => is_numeric($data['lng'] ?? '') ? (float) $data['lng'] : null,
        ]);
        $contactConfig->save();

        Flash::success('Información de contacto guardada.');
        return [];
    }

    public function onSaveSeo()
    {
        $tenant    = $this->getCurrentTenant();
        $seoConfig = SeoConfig::where('tenant_id', $tenant->id)->firstOrFail();
        $data      = post('SeoConfig', []);

        $seoConfig->fill([
            'title_format'        => $data['title_format']        ?: $seoConfig->title_format,
            'default_description' => $data['default_description'] ?: null,
            'google_analytics_id' => $data['google_analytics_id'] ?: null,
            'sitemap_enabled'     => (bool) ($data['sitemap_enabled'] ?? false),
            'robots_txt'          => $data['robots_txt']          ?? $seoConfig->robots_txt,
        ]);
        $seoConfig->save();

        // Commit deferred file binding (og_image)
        $sessionKey = post('_session_key', '');
        if ($sessionKey) {
            $seoConfig->commitDeferred($sessionKey);
        }

        Flash::success('Configuración SEO guardada.');
        return [];
    }

    // -------------------------------------------------------------------------
    // AJAX — Notification Channels
    // -------------------------------------------------------------------------

    public function onSaveChannel()
    {
        $tenant  = $this->getCurrentTenant();
        $id      = post('channel_id');
        $data    = post('Channel', []);

        if ($id) {
            $channel = NotificationChannel::forTenant($tenant->id)->findOrFail((int) $id);
        } else {
            $channel             = new NotificationChannel;
            $channel->tenant_id  = $tenant->id;
            $channel->sort_order = NotificationChannel::forTenant($tenant->id)->count() + 1;
        }

        $channel->label      = $data['label']                       ?? $channel->label;
        $channel->type       = $data['type']                        ?? $channel->type;
        $channel->is_enabled = (bool) ($data['is_enabled']          ?? false);
        $channel->config     = array_filter($data['config'] ?? [], fn($v) => $v !== null && $v !== '');
        $channel->save();

        Flash::success($id ? 'Canal actualizado.' : 'Canal creado.');

        return [
            '#channel-list' => $this->makePartial('channels_list', [
                'channels' => $this->getChannels($tenant->id),
            ]),
            '#channel-form-inner' => $this->makeChannelFormWidget(new NotificationChannel)->render(),
            '#channel_id_field'   => '<input type="hidden" name="channel_id" id="channel_id_field" value="">',
        ];
    }

    public function onEditChannel()
    {
        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        $channel = NotificationChannel::forTenant($tenant->id)->findOrFail($id);

        return [
            '#channel-form-inner' => $this->makeChannelFormWidget($channel)->render(),
            '#channel_id_field'   => '<input type="hidden" name="channel_id" id="channel_id_field" value="' . $id . '">',
        ];
    }

    public function onDeleteChannel()
    {
        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        NotificationChannel::forTenant($tenant->id)->findOrFail($id)->delete();

        Flash::success('Canal eliminado.');

        return [
            '#channel-list' => $this->makePartial('channels_list', [
                'channels' => $this->getChannels($tenant->id),
            ]),
        ];
    }

    public function onToggleChannel()
    {
        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        $channel = NotificationChannel::forTenant($tenant->id)->findOrFail($id);
        $channel->is_enabled = !$channel->is_enabled;
        $channel->save();

        return [
            '#channel-list' => $this->makePartial('channels_list', [
                'channels' => $this->getChannels($tenant->id),
            ]),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function getChannels(int $tenantId)
    {
        return NotificationChannel::forTenant($tenantId)->orderBy('sort_order')->get();
    }

    // -------------------------------------------------------------------------
    // Widget builders
    // -------------------------------------------------------------------------

    protected function makeBrandingWidget(Tenant $tenant): Form
    {
        // Atributos virtuales para precargar el form con el override actual
        // (theme_overrides.colors.*/fonts.*); no se guardan directo,
        // onSaveBranding() los recompone en theme_overrides (y los descarta
        // del modelo antes de save(), ver ahí el porqué).
        $colorOverrides = $tenant->theme_overrides['colors'] ?? [];
        $tenant->override_primary = $colorOverrides['primary'] ?? null;
        $tenant->override_accent  = $colorOverrides['accent'] ?? null;

        $fontOverrides = $tenant->theme_overrides['fonts'] ?? [];
        $tenant->override_font_heading  = $fontOverrides['heading'] ?? null;
        $tenant->override_font_heading2 = $fontOverrides['heading2'] ?? null;
        $tenant->override_font_body     = $fontOverrides['body'] ?? null;

        $config            = new \stdClass;
        $config->model     = $tenant;
        $config->arrayName = 'Branding';
        $config->alias     = 'brandingForm';
        $config->fields    = [
            'name' => [
                'label'    => 'Nombre del sitio',
                'type'     => 'text',
                'required' => true,
                'span'     => 'left',
            ],
            'primary_color' => [
                'label'       => 'Color principal (legacy)',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => '#3b82f6',
                'comment'     => 'Solo se usa si no hay un tema visual asignado abajo.',
            ],
            'logo' => [
                'label'       => 'Logo',
                'type'        => 'fileupload',
                'mode'        => 'image',
                'imageWidth'  => 400,
                'imageHeight' => 200,
                'span'        => 'left',
            ],
            'favicon' => [
                'label'       => 'Favicon',
                'type'        => 'fileupload',
                'mode'        => 'image',
                'imageWidth'  => 64,
                'imageHeight' => 64,
                'span'        => 'right',
                'comment'     => 'Recomendado: 32×32 o 64×64 px',
            ],
            '_palette' => [
                'label' => 'Paleta de colores',
                'type'  => 'section',
                'span'  => 'full',
            ],
            'design_theme_id' => [
                'label'       => 'Tema visual',
                'type'        => 'dropdown',
                'span'        => 'full',
                'placeholder' => 'Ninguno (usar color principal legacy)',
                'options'     => $tenant->getDesignThemeIdOptions(),
                'comment'     => 'Define la combinación de colores (claro y oscuro), tipografía y radio de esquinas del sitio.',
            ],
            'override_primary' => [
                'label'       => 'Personalizar color primario (opcional)',
                'type'        => 'colorpicker',
                'span'        => 'left',
                'comment'     => 'Sobreescribe el primario del tema elegido, en ambos modos.',
            ],
            'override_accent' => [
                'label'       => 'Personalizar color de acento (opcional)',
                'type'        => 'colorpicker',
                'span'        => 'right',
                'comment'     => 'Sobreescribe el acento del tema elegido, en ambos modos.',
            ],
            '_typography' => [
                'label' => 'Tipografía',
                'type'  => 'section',
                'span'  => 'full',
            ],
            'override_font_heading' => [
                'label'       => 'Fuente de encabezado principal (H1)',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => 'Ej: Plus Jakarta Sans',
                'comment'     => 'Nombre exacto de Google Fonts. Vacío = usa la del tema visual.',
            ],
            'override_font_heading2' => [
                'label'       => 'Fuente de subtítulos (H2-H6)',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => 'Ej: Plus Jakarta Sans',
                'comment'     => 'Nombre exacto de Google Fonts. Vacío = usa la del tema visual.',
            ],
            'override_font_body' => [
                'label'       => 'Fuente de texto',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => 'Ej: Inter',
                'comment'     => 'Nombre exacto de Google Fonts. Vacío = usa la del tema visual.',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }

    protected function makeContactInfoWidget(?ContactConfig $model): Form
    {
        $config            = new \stdClass;
        $config->model     = $model ?? new ContactConfig;
        $config->arrayName = 'ContactInfo';
        $config->alias     = 'contactInfoForm';
        $config->fields    = [
            'contact_email' => [
                'label'       => 'Email de contacto',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => 'info@tusitio.com',
            ],
            'phone' => [
                'label'       => 'Teléfono',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => '+591 70000000',
            ],
            'whatsapp' => [
                'label'       => 'WhatsApp',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => '+59170000000',
                'comment'     => 'Con código de país, sin espacios',
            ],
            'address' => [
                'label'       => 'Dirección',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => 'Av. Ejemplo 123, Ciudad',
            ],
            '_location' => [
                'label' => 'Ubicación en mapa (opcional)',
                'type'  => 'section',
            ],
            'lat' => [
                'label'       => 'Latitud',
                'type'        => 'number',
                'span'        => 'left',
                'placeholder' => '-17.783333',
                'step'        => 'any',
            ],
            'lng' => [
                'label'       => 'Longitud',
                'type'        => 'number',
                'span'        => 'right',
                'placeholder' => '-63.182222',
                'step'        => 'any',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }

    protected function makeSeoWidget(?SeoConfig $model): Form
    {
        $config            = new \stdClass;
        $config->model     = $model ?? new SeoConfig;
        $config->arrayName = 'SeoConfig';
        $config->alias     = 'seoForm';
        $config->fields    = [
            'title_format' => [
                'label'       => 'Formato del título',
                'type'        => 'text',
                'required'    => true,
                'span'        => 'left',
                'comment'     => '%s = título de la página · {name} = nombre del sitio',
                'placeholder' => '%s | {name}',
            ],
            'sitemap_enabled' => [
                'label'   => 'Habilitar sitemap XML',
                'type'    => 'checkbox',
                'span'    => 'right',
                'default' => true,
            ],
            'default_description' => [
                'label'       => 'Descripción por defecto',
                'type'        => 'textarea',
                'span'        => 'full',
                'size'        => 'small',
                'placeholder' => 'Descripción del sitio para buscadores.',
            ],
            'og_image' => [
                'label'       => 'Imagen Open Graph',
                'type'        => 'fileupload',
                'mode'        => 'image',
                'imageWidth'  => 1200,
                'imageHeight' => 630,
                'span'        => 'left',
                'comment'     => 'Recomendado: 1200×630 px',
            ],
            'google_analytics_id' => [
                'label'       => 'Google Analytics ID',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => 'G-XXXXXXXXXX',
            ],
            'robots_txt' => [
                'label'    => 'robots.txt',
                'type'     => 'codeeditor',
                'language' => 'text',
                'span'     => 'full',
                'size'     => 'small',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }

    protected function makeChannelFormWidget(NotificationChannel $model): Form
    {
        $config            = new \stdClass;
        $config->model     = $model;
        $config->arrayName = 'Channel';
        $config->alias     = 'channelForm';
        $config->form      = '$/aero/sites/models/notificationchannel/fields.yaml';

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }
}
