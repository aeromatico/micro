<?php namespace Aero\Wapi;

use Aero\Hello\Models\Profile;
use Backend;
use Crypt;
use Event;
use Route;
use System\Classes\PluginBase;

/**
 * Segundo driver de mensajería para Aero.Hello: WhatsApp Web propio (sesión
 * por QR vía https://github.com/aeromatico/wapi, self-hosted en este mismo
 * servidor) como alternativa a Zernio/WhatsApp Business API. Se registra en
 * Aero\Hello\Classes\Notifications\MessageDispatcher bajo el handle 'wapi';
 * el resto del pipeline de envío/recepción de Hello no sabe ni necesita
 * saber que existe.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Hello'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.wapi::lang.plugin.name',
            'description' => 'aero.wapi::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-whatsapp',
            'homepage'    => 'https://github.com/aeromatico/wapi',
        ];
    }

    public function boot(): void
    {
        $this->registerChannelDriver();
        $this->registerWebhookRoute();
        $this->extendProfileModel();
        $this->extendProfileForm();
    }

    /**
     * `Profile` es de Aero.Hello y no sabe de wapi — no se le puede agregar
     * `wapi_api_key` a su $fillable/$encryptable estáticos sin acoplar Hello
     * a este plugin. Se agrega por extensión, mismo patrón que usa
     * Aero.Sites para colgarle `belongsTo['tenant']` a este mismo modelo.
     *
     * $encryptable es protected en el trait Encryptable, así que no se puede
     * pushear un campo nuevo ahí desde afuera; se replica el cifrado a mano
     * con los mismos dos eventos que usa el trait.
     */
    protected function extendProfileModel(): void
    {
        Profile::extend(function ($model) {
            $model->addFillable('wapi_api_key');

            $model->bindEvent('model.beforeSetAttribute', function ($key, $value) {
                if ($key === 'wapi_api_key' && $value !== null && $value !== '') {
                    return Crypt::encrypt($value);
                }
            });

            $model->bindEvent('model.beforeGetAttribute', function ($key) use ($model) {
                if ($key === 'wapi_api_key' && !empty($model->attributes[$key])) {
                    return Crypt::decrypt($model->attributes[$key]);
                }
            });
        });
    }

    /**
     * Inyecta el campo en el form de Profiles (Aero.Hello) sin tocar su
     * fields.yaml — mismo motivo que extendProfileModel(): ese archivo es de
     * Hello y no debería listar campos de un plugin que puede no estar
     * instalado.
     */
    protected function extendProfileForm(): void
    {
        Event::listen('backend.form.extendFields', function ($widget) {
            if (!$widget->model instanceof Profile) {
                return;
            }

            $widget->addFields([
                'wapi_api_key' => [
                    'label'   => 'aero.wapi::lang.settings.api_key',
                    'type'    => 'text',
                    'span'    => 'right',
                    'comment' => 'aero.wapi::lang.profile.api_key_comment',
                    'trigger' => [
                        'action'    => 'show',
                        'field'     => 'use_own_credentials',
                        'condition' => 'checked',
                    ],
                ],
            ]);
        });
    }

    protected function registerChannelDriver(): void
    {
        Event::listen('aero.hello.registerChannelDrivers', function ($dispatcher) {
            $dispatcher->register('wapi', \Aero\Wapi\Classes\Notifications\WapiChannelDriver::class);
        });
    }

    protected function registerWebhookRoute(): void
    {
        // Público por necesidad (lo llama wapi): la firma HMAC más el
        // throttle son la única defensa, igual que el webhook de Zernio.
        Route::post('api/v1/wapi/webhooks/{instanceId}', [
            \Aero\Wapi\Http\Controllers\Api\WapiWebhookController::class, 'handle',
        ])->middleware('throttle:300,1');
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'aero.wapi::lang.settings.label',
                'description' => 'aero.wapi::lang.settings.description',
                'category'    => 'Sistema',
                'icon'        => 'icon-whatsapp',
                'class'       => \Aero\Wapi\Models\Settings::class,
                'order'       => 521,
                'permissions' => ['aero.hello.superadmin'],
                'keywords'    => 'wapi whatsapp web whatsapp-web.js qr',
            ],
        ];
    }

    /**
     * Entrada propia en vez de colgar de Hello: registerNavigation() de
     * Hello vive en otro plugin y no hay forma limpia de inyectarle un
     * sideMenu desde acá. Reutiliza el permiso de Hello para no pedir uno
     * nuevo (ver el mismo criterio en registerSettings()).
     */
    public function registerNavigation(): array
    {
        return [
            'wapi' => [
                'label'       => 'aero.wapi::lang.menu.wapi',
                'url'         => Backend::url('aero/wapi/instances'),
                'icon'        => 'icon-mobile-phone',
                'permissions' => ['aero.hello.superadmin'],
                'order'       => 211,
                'sideMenu'    => [
                    'instances' => [
                        'label'       => 'aero.wapi::lang.menu.instances',
                        'icon'        => 'icon-qrcode',
                        'url'         => Backend::url('aero/wapi/instances'),
                        'permissions' => ['aero.hello.superadmin'],
                    ],
                ],
            ],
        ];
    }
}
