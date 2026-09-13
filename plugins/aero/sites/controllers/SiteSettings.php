<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\ContactConfig;
use Aero\Sites\Models\ContactSubmission;
use Aero\Notify\Models\Channel;
use Aero\Sites\Models\SeoConfig;
use Aero\Sites\Traits\HasBrandingForm;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;

class SiteSettings extends Controller
{
    use ResolvesCurrentTenant, HasBrandingForm;

    public $requiredPermissions = ['aero.sites.manage_seo'];

    public ?Form $contactInfoWidget   = null;
    public ?Form $contactConfigWidget = null;
    public ?Form $seoWidget           = null;
    public ?Form $channelFormWidget   = null;
    public ?Form $generalWidget       = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sitio-web', 'configuracion');
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

        $this->generalWidget       = $this->makeGeneralWidget($tenant);
        $this->contactInfoWidget   = $this->makeContactInfoWidget($contactConfig);
        $this->contactConfigWidget = $this->makeContactConfigWidget($contactConfig);
        $this->seoWidget           = $this->makeSeoWidget($seoConfig);

        // Aero.Sites no requiere Aero.Notify (es al revés): sin el plugin
        // instalado, la sección de canales simplemente no se muestra.
        if (class_exists(Channel::class)) {
            $this->channelFormWidget = $this->makeChannelFormWidget(new Channel);
            $this->vars['channels']  = $this->getChannels($tenant->id);
        } else {
            $this->vars['channels'] = collect();
        }

        $this->vars['tenant']        = $tenant;
        $this->vars['contactConfig'] = $contactConfig;
        $this->vars['seoConfig']     = $seoConfig;
        $this->vars['submissions']   = ContactSubmission::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    // -------------------------------------------------------------------------
    // AJAX — General
    // -------------------------------------------------------------------------

    public function onSaveGeneral()
    {
        $tenant = $this->getCurrentTenant();
        $data   = post('General', []);

        $tenant->niche_type = $data['niche_type'] ?? $tenant->niche_type;
        $tenant->save();

        Flash::success('Rubro guardado correctamente.');
        return [];
    }

    // -------------------------------------------------------------------------
    // AJAX — Contacto
    // -------------------------------------------------------------------------

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
        $this->assertChannelsAvailable();

        $tenant  = $this->getCurrentTenant();
        $id      = post('channel_id');
        $data    = post('Channel', []);

        if ($id) {
            $channel = Channel::forTenant($tenant->id)->findOrFail((int) $id);
        } else {
            $channel             = new Channel;
            $channel->tenant_id  = $tenant->id;
            $channel->sort_order = Channel::forTenant($tenant->id)->count() + 1;
        }

        $channel->label      = $data['label']                       ?? $channel->label;
        $channel->channel    = $data['channel']                     ?? $channel->channel;
        $channel->is_enabled = (bool) ($data['is_enabled']          ?? false);
        $channel->config     = array_filter($data['config'] ?? [], fn($v) => $v !== null && $v !== '');
        $channel->save();

        Flash::success($id ? 'Canal actualizado.' : 'Canal creado.');

        return [
            '#channel-list' => $this->makePartial('channels_list', [
                'channels' => $this->getChannels($tenant->id),
            ]),
            '#channel-form-inner' => $this->makeChannelFormWidget(new Channel)->render(),
            '#channel_id_field'   => '<input type="hidden" name="channel_id" id="channel_id_field" value="">',
        ];
    }

    public function onEditChannel()
    {
        $this->assertChannelsAvailable();

        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        $channel = Channel::forTenant($tenant->id)->findOrFail($id);

        return [
            '#channel-form-inner' => $this->makeChannelFormWidget($channel)->render(),
            '#channel_id_field'   => '<input type="hidden" name="channel_id" id="channel_id_field" value="' . $id . '">',
        ];
    }

    public function onDeleteChannel()
    {
        $this->assertChannelsAvailable();

        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        Channel::forTenant($tenant->id)->findOrFail($id)->delete();

        Flash::success('Canal eliminado.');

        return [
            '#channel-list' => $this->makePartial('channels_list', [
                'channels' => $this->getChannels($tenant->id),
            ]),
        ];
    }

    public function onToggleChannel()
    {
        $this->assertChannelsAvailable();

        $tenant  = $this->getCurrentTenant();
        $id      = (int) post('id');
        $channel = Channel::forTenant($tenant->id)->findOrFail($id);
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
        return Channel::forTenant($tenantId)->orderBy('sort_order')->get();
    }

    protected function assertChannelsAvailable(): void
    {
        if (!class_exists(Channel::class)) {
            throw new \ApplicationException('Aero.Notify no está instalado: no se pueden administrar canales.');
        }
    }

    // -------------------------------------------------------------------------
    // Widget builders
    // -------------------------------------------------------------------------

    protected function makeGeneralWidget(\Aero\Sites\Models\Tenant $tenant): Form
    {
        $config            = new \stdClass;
        $config->model     = $tenant;
        $config->arrayName = 'General';
        $config->alias     = 'generalForm';
        $config->fields    = [
            'niche_type' => [
                'label'   => 'Rubro del negocio',
                'type'    => 'dropdown',
                'span'    => 'left',
                'comment' => 'Afecta el prompt sugerido al generar contenido con IA y las recomendaciones de temas visuales. No reescribe el contenido ya generado.',
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

    protected function makeChannelFormWidget(Channel $model): Form
    {
        $config            = new \stdClass;
        $config->model     = $model;
        $config->arrayName = 'Channel';
        $config->alias     = 'channelForm';
        $config->form      = '$/aero/notify/models/channel/inline_fields.yaml';

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }
}
