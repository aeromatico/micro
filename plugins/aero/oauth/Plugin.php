<?php namespace Aero\Oauth;

use Aero\Oauth\Classes\Providers\ProviderRegistry;
use Aero\Oauth\Models\Settings;
use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Acceso con proveedores externos (hoy solo Google) para usuarios del backend,
 * y almacén de tokens para que otros plugins (p. ej. aero/sheets) hablen con
 * las APIs del proveedor en nombre de la persona. Independiente: no requiere
 * ningún otro plugin; los demás se acoplan con eventos aero.oauth.*.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Acceso con proveedores (OAuth)',
            'description' => 'Iniciar sesión con Google y conexión de cuentas con permisos incrementales por plugin.',
            'author'      => 'Aero',
            'icon'        => 'icon-key',
        ];
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        // Botones en la pantalla de login del backend.
        Event::listen('backend.auth.extendSigninView', function () {
            $buttons = [];
            foreach (ProviderRegistry::all() as $provider) {
                if ($provider->code() === 'google' && !Settings::googleLoginEnabled()) {
                    continue;
                }
                if ($provider->isConfigured()) {
                    $buttons[] = $provider;
                }
            }

            if (!$buttons) {
                return '';
            }

            $providers = $buttons;
            ob_start();
            include __DIR__ . '/views/signin_buttons.php';

            return ob_get_clean();
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.oauth.use' => [
                'tab'   => 'Acceso con proveedores',
                'label' => 'Vincular su propia cuenta de Google (y otros proveedores)',
            ],
            'aero.oauth.superadmin' => [
                'tab'   => 'Acceso con proveedores',
                'label' => 'Administrar el acceso con proveedores y ver las cuentas vinculadas de todos',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'oauth' => [
                'label'       => 'Cuentas conectadas',
                'url'         => Backend::url('aero/oauth/connections'),
                'icon'        => 'icon-key',
                'iconSvg'     => null,
                'permissions' => ['aero.oauth.use', 'aero.oauth.superadmin'],
                'order'       => 905,
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'Acceso con Google',
                'description' => 'Interruptores del inicio de sesión con proveedores y URI de redirección.',
                'category'    => 'Sistema',
                'icon'        => 'icon-key',
                'class'       => Models\Settings::class,
                'order'       => 515,
                'permissions' => ['aero.oauth.superadmin'],
            ],
        ];
    }
}
