<?php namespace Aero\Workspaces\Classes;

/**
 * Tenant de quien mira el panel (o el sitio elegido por un superadmin).
 * Aero.Sites es dependencia blanda: sin él, nadie es «tenant» y las pantallas
 * del cliente quedan cerradas (falla cerrado).
 */
class CurrentTenant
{
    public static function id(): ?int
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return null;
        }

        return (new class {
            use \Aero\Sites\Traits\ResolvesCurrentTenant;

            public function id(): ?int
            {
                return $this->getCurrentTenantId();
            }
        })->id();
    }
}
