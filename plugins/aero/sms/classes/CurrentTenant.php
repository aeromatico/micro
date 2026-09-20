<?php namespace Aero\Sms\Classes;

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
        return (bool) BackendAuth::getUser()?->hasAccess('aero.sms.superadmin');
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

        return self::$cached = (new class {
            use \Aero\Sites\Traits\ResolvesCurrentTenant;

            public function id(): ?int
            {
                return $this->getCurrentTenantId();
            }
        })->id();
    }

    public static function name(?int $id = null): ?string
    {
        $id ??= self::id();

        return $id && class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::find($id)?->name
            : null;
    }
}
