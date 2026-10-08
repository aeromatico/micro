<?php namespace Aero\Finance\Classes;

use BackendAuth;

/**
 * Quién está mirando el panel. Un superadmin ve todo; un usuario de tenant
 * solo lo suyo. Aero.Sites es dependencia blanda: sin él nadie es "tenant".
 */
class CurrentTenant
{
    protected static ?int $cached = null;
    protected static bool $resolved = false;

    public static function isAdmin(): bool
    {
        return (bool) BackendAuth::getUser()?->hasAccess('aero.finance.superadmin');
    }

    /** Descripción del libro en pantalla: «portal» (tenant master) o un negocio. */
    public static function booksLabel(): string
    {
        $id = self::id();
        if (!$id || !class_exists(\Aero\Sites\Models\Tenant::class)) {
            return 'No se pudo determinar el libro contable.';
        }

        $name = \Aero\Sites\Models\Tenant::whereKey($id)->value('name') ?: ('#' . $id);

        return self::isPortal($id)
            ? 'Está en la contabilidad del PORTAL (' . $name . '). Es independiente de la de los tenants.'
            : 'Está en la contabilidad del negocio «' . $name . '», NO en la del portal. Cada tenant gobierna la suya.';
    }

    /** ¿Es el libro del portal (tenant master)? */
    public static function isPortal(?int $tenantId = null): bool
    {
        $tenantId ??= self::id();

        return $tenantId && class_exists(\Aero\Sites\Classes\PlatformServicesBlock::class)
            && \Aero\Sites\Classes\PlatformServicesBlock::masterTenantId() === (int) $tenantId;
    }

    public static function id(): ?int
    {
        if (self::$resolved) {
            return self::$cached;
        }

        self::$resolved = true;

        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return self::$cached = null;
        }

        // El host manda: entrar por master.market.com.bo es estar en el master,
        // aunque el selector de sitios del backend tenga otro (el master no
        // tiene sitio y Sites da prioridad a ese selector sobre el subdominio).
        if ($id = self::fromHost()) {
            return self::$cached = $id;
        }

        try {
            return self::$cached = (new class {
                use \Aero\Sites\Traits\ResolvesCurrentTenant;

                public function id(): ?int
                {
                    return $this->getCurrentTenantId();
                }
            })->id();
        } catch (\Throwable $e) {
            return self::$cached = null; // falla cerrado
        }
    }

    /** Tenant del subdominio actual, solo si el usuario del panel tiene acceso a él. */
    public static function fromHost(?string $host = null): ?int
    {
        $user = BackendAuth::getUser();
        if (!$user || !class_exists(\Aero\Sites\Models\Tenant::class)) {
            return null;
        }

        try {
            $tenant = \Aero\Sites\Models\Tenant::resolveFromSubdomain($host ?? request()->getHost());

            return $tenant && ($user->is_superuser || $tenant->isAccessibleBy($user)) ? (int) $tenant->id : null;
        } catch (\Throwable $e) {
            return null; // falla cerrado
        }
    }

    /**
     * Tenants a los que el usuario del panel pertenece (el actual primero).
     * Un usuario con varios negocios no siempre resuelve al que espera
     * (sin subdominio Sites elige uno), así que el puesto de acceso busca
     * la credencial en todos los suyos. Solo los suyos: nunca los ajenos.
     */
    public static function accessibleIds(): array
    {
        $ids = array_filter([self::id()]);

        $user = BackendAuth::getUser();
        if ($user && class_exists(\Aero\Sites\Models\TenantUser::class)) {
            try {
                $ids = array_merge($ids, \Aero\Sites\Models\TenantUser::where('user_id', $user->id)->pluck('tenant_id')->all());
            } catch (\Throwable $e) {
                // falla cerrado: solo el tenant resuelto
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
