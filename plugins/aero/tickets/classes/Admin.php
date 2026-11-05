<?php namespace Aero\Tickets\Classes;

/** Permisos globales de la plataforma. `aero.tickets.superadmin` lo incluye todo; los demás son por área. */
class Admin
{
    /** @param string $area view | organizers | events | finance | settings */
    public static function can(string $area): bool
    {
        $u = \BackendAuth::getUser();

        return (bool) $u?->hasAnyAccess(['aero.tickets.admin.' . $area, 'aero.tickets.superadmin']);
    }

    /** ¿Ve todos los espacios de trabajo en pagos/órdenes? (superadmin o rol de finanzas) */
    public static function seesAllFinance(): bool
    {
        return self::can('finance');
    }
}
