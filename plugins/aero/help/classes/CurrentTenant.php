<?php namespace Aero\Help\Classes;

use BackendAuth;

/**
 * Quién está mirando el panel. Aero.Sites es dependencia blanda: sin él, o si
 * no puede resolver el negocio, el tenant es null y la ayuda sigue funcionando
 * con el contenido de la plataforma.
 */
class CurrentTenant
{
    protected static ?int $cached = null;
    protected static bool $resolved = false;

    public static function isAdmin(): bool
    {
        return (bool) BackendAuth::getUser()?->hasAccess('aero.help.superadmin');
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
}
