<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Tenant;
use Aero\Sites\Models\TenantUser;
use Backend\Models\User;

/**
 * Bloque «Servicios de la plataforma» (Aero.Services) del editor visual.
 *
 * Solo existe para el tenant master: lo ofrece el editor únicamente a
 * superadmins y admins de ese tenant, y el render lo vuelve a comprobar con
 * el host de la petición (el marcador vive en HTML editable, así que nunca se
 * confía en él).
 *
 * El marcador guarda la configuración en atributos data-*:
 *   data-categories="slug,slug"   filtro por categorías (cualquiera de ellas)
 *   data-plugins="Aero.Shop,…"    filtro por conexiones (plugins ligados al servicio)
 *   data-allow="slug,…"           lista blanca: se incluyen aunque no pasen los filtros
 *   data-deny="slug,…"            lista negra: nunca se muestran (gana siempre)
 * Con filtros, pasan los servicios que cumplan categorías Y conexiones (las
 * que estén definidas). Sin filtros ni lista blanca salen todos los activos;
 * solo con lista blanca, únicamente esos.
 */
class PlatformServicesBlock
{
    public const MASTER_HANDLE = 'master';

    public static function servicesAvailable(): bool
    {
        return class_exists(\Aero\Services\Models\Service::class);
    }

    public static function isMaster(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->handle === self::MASTER_HANDLE;
    }

    /** Superadmin, o propietario/admin del tenant master. */
    public static function userCanUse(?User $user, ?Tenant $tenant): bool
    {
        if (!$user || !self::isMaster($tenant)) {
            return false;
        }

        if ($user->is_superuser || $user->hasAccess('aero.sites.superadmin')) {
            return true;
        }

        return (int) $tenant->backend_user_id === (int) $user->id
            || TenantUser::where('tenant_id', $tenant->id)->where('user_id', $user->id)->where('role', 'admin')->exists();
    }

    /** Datos para los selectores del editor, o null si este usuario/página no lo puede usar. */
    public static function forEditor(?User $user, ?Tenant $tenant): ?array
    {
        if (!self::servicesAvailable() || !self::userCanUse($user, $tenant)) {
            return null;
        }

        return self::forEditorOptions();
    }

    /** Opciones de categorías, conexiones y servicios (sin comprobar permisos: quien llama ya lo hizo). */
    public static function forEditorOptions(): array
    {
        $services = \Aero\Services\Models\Service::active()->orderBy('name')->get(['id', 'name', 'slug', 'plugin_links']);

        $plugins = [];
        foreach ($services as $s) {
            foreach ((array) $s->plugin_links as $link) {
                if (!empty($link['plugin'])) {
                    $plugins[$link['plugin']] = (string) $link['plugin'];
                }
            }
        }
        ksort($plugins);

        return [
            'categories' => \Aero\Services\Models\Category::active()->orderBy('sort_order')->orderBy('name')
                ->get(['slug', 'name'])->map(fn ($c) => ['value' => $c->slug, 'label' => $c->name])->all(),
            'plugins'    => array_map(fn ($code) => ['value' => $code, 'label' => $code], array_values($plugins)),
            'services'   => $services->map(fn ($s) => ['value' => $s->slug, 'label' => $s->name])->all(),
        ];
    }

    /** Id del tenant master (null si no existe). */
    public static function masterTenantId(): ?int
    {
        $id = Tenant::where('handle', self::MASTER_HANDLE)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Servicios activos que cumplen la configuración (categories, plugins,
     * allow, deny: cadenas "a,b,c" o arreglos). Lo usan el bloque del editor
     * visual y el nodo de Workflows, así ambos filtran idéntico.
     */
    public static function select(array $attrs = []): \Illuminate\Support\Collection
    {
        if (!self::servicesAvailable()) {
            return collect();
        }

        $list = function (string $key) use ($attrs) {
            $raw = $attrs[$key] ?? '';
            $items = is_array($raw) ? $raw : explode(',', (string) $raw);

            return array_values(array_filter(array_map('trim', $items)));
        };
        $categories = $list('categories');
        $plugins    = $list('plugins');
        $allow      = $list('allow');
        $deny       = $list('deny');

        $services = \Aero\Services\Models\Service::active()->with('categories')->orderBy('sort_order')->orderBy('name')->get();

        return $services->filter(function ($s) use ($categories, $plugins, $allow, $deny) {
            if (in_array($s->slug, $deny, true)) {
                return false;
            }
            if (!$categories && !$plugins && !$allow) {
                return true;
            }
            if (in_array($s->slug, $allow, true)) {
                return true;
            }
            if (!$categories && !$plugins) {
                return false;
            }

            $okCats = !$categories || $s->categories->pluck('slug')->intersect($categories)->isNotEmpty();
            $linked = collect((array) $s->plugin_links)->pluck('plugin')->all();
            $okPlugins = !$plugins || array_intersect($plugins, $linked) !== [];

            return $okCats && $okPlugins;
        })->values();
    }

    /** Enlace público absoluto de un servicio (para canales fuera del sitio, como WhatsApp). */
    public static function serviceUrl($service): string
    {
        $root = \Aero\Sites\Models\RootDomain::active()->orderBy('id')->value('domain');

        return $root ? 'https://' . $root . '/plugin/' . $service->slug : url('plugin/' . $service->slug);
    }

    /** HTML del bloque. $attrs son los data-* del marcador, ya decodificados. */
    public static function render(array $attrs = []): string
    {
        if (!self::servicesAvailable()) {
            return '';
        }

        $tenant = Tenant::resolveFromDomain(request()->getHost());
        if (!self::isMaster($tenant)) {
            return '';
        }

        $services = self::select($attrs);

        if ($services->isEmpty()) {
            return '';
        }

        $variant = in_array($attrs['variant'] ?? '', ['1', '2'], true) ? $attrs['variant'] : '1';

        ob_start();
        include __DIR__ . '/../components/platformservices/cards.htm';

        return (string) ob_get_clean();
    }
}
