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
    }

    // -------------------------------------------------------------------------
    // AJAX — Branding
    // -------------------------------------------------------------------------

    public function index_onSaveBranding()
    {
        $tenant = $this->getCurrentTenant();
        $data   = post('Branding', []);

        $tenant->name          = $data['name']          ?? $tenant->name;
        $tenant->primary_color = $data['primary_color'] ?? $tenant->primary_color;
        $tenant->save();

        // Commit deferred file bindings (logo, favicon)
        $sessionKey = post('_session_key', '');
        if ($sessionKey) {
            $tenant->commitDeferred($sessionKey);
        }

        Flash::success('Branding guardado correctamente.');
        return [];
    }

    public function index_onSaveContactInfo()
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

    public function index_onSaveSeo()
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

    public function index_onSaveChannel()
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
            '#channel-list' => $this->makePartial('_channels_list', [
                'channels' => $this->getChannels($tenant->id),
            ]),
            '#channel-form-inner' => $this->makeChannelFormWidget(new NotificationChannel)->render(),
            '#channel_id_field'   => '<input type="hidden" name="channel_id" id="channel_id_field" value="">',
        ];
    }

    public function index_onEditChannel()
    {
        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        $channel = NotificationChannel::forTenant($tenant->id)->findOrFail($id);

        return [
            '#channel-form-inner' => $this->makeChannelFormWidget($channel)->render(),
            '#channel_id_field'   => '<input type="hidden" name="channel_id" id="channel_id_field" value="' . $id . '">',
        ];
    }

    public function index_onDeleteChannel()
    {
        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        NotificationChannel::forTenant($tenant->id)->findOrFail($id)->delete();

        Flash::success('Canal eliminado.');

        return [
            '#channel-list' => $this->makePartial('_channels_list', [
                'channels' => $this->getChannels($tenant->id),
            ]),
        ];
    }

    public function index_onToggleChannel()
    {
        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        $channel = NotificationChannel::forTenant($tenant->id)->findOrFail($id);
        $channel->is_enabled = !$channel->is_enabled;
        $channel->save();

        return [
            '#channel-list' => $this->makePartial('_channels_list', [
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
                'label'       => 'Color principal',
                'type'        => 'text',
                'span'        => 'right',
                'placeholder' => '#3b82f6',
                'comment'     => 'Hexadecimal #rrggbb',
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
