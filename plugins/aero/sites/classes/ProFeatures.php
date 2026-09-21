<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Settings;
use Backend\Models\UserRole;
use System\Classes\PluginManager;

/**
 * Funciones PRO del panel. El superadmin marca (Settings → Sites) qué pantallas
 * de cada plugin son PRO; el menú sigue visible para todos los tenants, pero un
 * tenant_admin "regular" que entra a una pantalla PRO ve la invitación a
 * mejorar el plan en vez de la pantalla. Solo el rol tenant_admin queda
 * limitado: tenant_admin_pro, superadmin y roles a medida pasan sin filtro.
 *
 * Clave de una función = "vendor/plugin/controller" en minúsculas, la misma
 * del segmento de URL del backend (aero/hello/accounts).
 */
class ProFeatures
{
    public const ROLE_REGULAR = 'tenant_admin';
    public const ROLE_PRO     = 'tenant_admin_pro';

    /**
     * Pantallas de menú que un tenant_admin puede abrir, por plugin:
     * [clave => ['plugin' => 'Aero.Hello', 'label' => 'Mensajería › Conectar']].
     * Se lee registerNavigation() de cada plugin (sin filtrar por el usuario
     * actual, para que el superadmin vea el catálogo completo).
     */
    public static function catalog(): array
    {
        $role = UserRole::where('code', self::ROLE_REGULAR)->first();
        $granted = $role->permissions ?? [];
        $out = [];

        $all = PluginManager::instance()->getRegistrationMethodValues('registerNavigation');
        foreach ($all as $pluginCode => $menus) {
            foreach ((array) $menus as $menuCode => $menu) {
                $menuLabel = static::label($menu['label'] ?? $menuCode);
                $items = $menu['sideMenu'] ?? [$menuCode => $menu];

                foreach ($items as $itemCode => $item) {
                    $key = static::keyFromUrl($item['url'] ?? '');
                    $perms = (array) ($item['permissions'] ?? []);
                    if (!$key || !static::grantedTo($granted, $perms)) {
                        continue;
                    }
                    $itemLabel = static::label($item['label'] ?? $itemCode);
                    $out[$key] = [
                        'plugin' => $pluginCode,
                        'label'  => $menuLabel === $itemLabel ? $itemLabel : "{$menuLabel} › {$itemLabel}",
                    ];
                }
            }
        }

        // Elementos finos (opción de un campo, campo suelto, PWA…) que cada
        // plugin declara: ['aero/x/ctrl#campo.opcion' => ['plugin'=>..,'label'=>..]].
        foreach (array_filter((array) \Event::fire('aero.sites.registerProFeatures')) as $declared) {
            foreach ((array) $declared as $key => $info) {
                $out[strtolower($key)] = $info;
            }
        }

        ksort($out);
        return $out;
    }

    /**
     * Bloquea en un formulario los elementos PRO de su pantalla cuando el
     * usuario es un tenant_admin regular: "#campo.opcion" relabela la opción
     * con "🔒 PRO"; "#campo" deja el campo de solo lectura. Es solo la capa
     * visual — cada modelo debe validar también al guardar (ver blocks()).
     */
    public static function lockFormElements($form, $controller): void
    {
        $screen = static::keyForController($controller);
        $user = \BackendAuth::getUser();

        foreach (static::proKeys() as $key) {
            if (!str_starts_with($key, $screen . '#') || !static::blocks($user, $key)) {
                continue;
            }
            [$name, $option] = array_pad(explode('.', substr($key, strlen($screen) + 1), 2), 2, null);
            $field = $form->getField($name);
            if (!$field) {
                continue;
            }
            if ($option === null) {
                $field->disabled = true;
                $field->label = trans($field->label) . ' 🔒 PRO';
                continue;
            }
            $options = $field->options();
            foreach ($options as $value => $text) {
                if (strtolower((string) $value) === $option) {
                    $options[$value] = trans($text) . ' 🔒 PRO';
                }
            }
            $field->options($options);
        }
    }

    public static function roleCodeForPlan(?\Aero\Sites\Models\Plan $plan): string
    {
        return $plan?->is_pro ? self::ROLE_PRO : self::ROLE_REGULAR;
    }

    /**
     * ¿El plan del tenant del usuario excluye el plugin de esta pantalla?
     * Solo afecta a tenant_admin / tenant_admin_pro (nunca al superadmin ni a
     * roles a medida) y solo a plugins gestionables desde Planes; sin plan
     * asignado o con plan sin restricción de plugins, pasa todo.
     */
    public static function blocksByPlan($user, $controller): bool
    {
        if (!$user || $user->is_superuser
            || !in_array(optional($user->role)->code, [self::ROLE_REGULAR, self::ROLE_PRO], true)) {
            return false;
        }

        if (!preg_match('/^Aero\\\\(\w+)\\\\Controllers\\\\/', get_class($controller), $m)) {
            return false;
        }
        $plugin = 'Aero.' . $m[1];

        if (in_array($plugin, \Aero\Sites\Models\Plan::ALWAYS_ALLOWED, true)) {
            return false;
        }

        $tenant = \Aero\Sites\Models\Tenant::resolveForBackendUser($user);
        $plan = $tenant?->plan;

        return $plan
            && in_array($plugin, \Aero\Sites\Models\Plan::manageablePlugins(), true)
            && !$plan->allowsPlugin($plugin);
    }

    public static function proKeys(): array
    {
        return array_map('strtolower', (array) Settings::get('pro_features', []));
    }

    public static function isProKey(string $key): bool
    {
        return in_array(strtolower($key), static::proKeys(), true);
    }

    /** ¿Debe ver la invitación a PRO en vez de la pantalla $key? */
    public static function blocks($user, string $key): bool
    {
        return $user
            && !$user->is_superuser
            && optional($user->role)->code === self::ROLE_REGULAR
            && static::isProKey($key);
    }

    public static function keyForController($controller): string
    {
        return strtolower(str_replace('\\', '/', preg_replace('/\\\\Controllers\\\\/', '\\', get_class($controller))));
    }

    protected static function keyFromUrl(string $url): ?string
    {
        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        $backendUri = trim(\Backend::uri(), '/');
        if ($backendUri !== '' && str_starts_with($path, $backendUri . '/')) {
            $path = substr($path, strlen($backendUri) + 1);
        }
        $parts = explode('/', strtolower($path));
        return count($parts) >= 3 ? implode('/', array_slice($parts, 0, 3)) : null;
    }

    protected static function grantedTo(array $granted, array $perms): bool
    {
        if (!$perms) {
            return true;
        }
        foreach ($perms as $p) {
            if (!empty($granted[$p])) {
                return true;
            }
        }
        return false;
    }

    protected static function label($label): string
    {
        return e(trans((string) $label));
    }
}
