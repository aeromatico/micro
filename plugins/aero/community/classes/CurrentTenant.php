<?php namespace Aero\Community\Classes;

use BackendAuth;

/**
 * Quién está mirando el panel. Un superadmin (el equipo de la plataforma)
 * modera todo; un usuario de tenant ve lo público y lo suyo.
 */
class CurrentTenant
{
    protected static ?int $cached = null;
    protected static bool $resolved = false;

    public static function isAdmin(): bool
    {
        return (bool) BackendAuth::getUser()?->hasAccess('aero.community.superadmin');
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

        // Si Sites no puede resolverlo (sin tablas, sin sesión) falla cerrado.
        try {
            return self::$cached = (new class {
                use \Aero\Sites\Traits\ResolvesCurrentTenant;

                public function id(): ?int
                {
                    return $this->getCurrentTenantId();
                }
            })->id();
        } catch (\Throwable) {
            return self::$cached = null;
        }
    }

    public static function name(?int $tenantId): ?string
    {
        if (!$tenantId || !class_exists(\Aero\Sites\Models\Tenant::class)) {
            return null;
        }

        try {
            return \Aero\Sites\Models\Tenant::find($tenantId)?->name;
        } catch (\Throwable) {
            return null;
        }
    }
}
