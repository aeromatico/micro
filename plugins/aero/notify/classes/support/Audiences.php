<?php namespace Aero\Notify\Classes\Support;

/**
 * Los tres niveles de audiencia del gateway, más los dos casos que no son un
 * "nivel" sino una forma de señalar destinatarios concretos.
 *
 * Los resolvers que traducen cada código a destinatarios reales llegan en la
 * fase 2; el registro es extensible vía el evento aero.notify.registerAudiences.
 */
class Audiences
{
    /** Nivel 1: operadores de la plataforma. */
    public const SUPERADMIN = 'superadmin';

    /** Nivel 2: dueños y moderadores del tenant. */
    public const TENANT_ADMIN = 'tenant_admin';

    /** Nivel 3: clientes, equipo y contactos del tenant. */
    public const TENANT_USER = 'tenant_user';

    /** El sujeto del evento, pasado por quien dispara. */
    public const ACTOR = 'actor';

    /** Direcciones sueltas: invitados, buzones de área. */
    public const ADHOC = 'adhoc';

    public static function options(): array
    {
        return [
            self::SUPERADMIN   => 'Superadministradores',
            self::TENANT_ADMIN => 'Administradores del tenant',
            self::TENANT_USER  => 'Usuarios del tenant',
            self::ACTOR        => 'Sujeto del evento',
            self::ADHOC        => 'Destinatarios sueltos',
        ];
    }

    public static function codes(): array
    {
        return array_keys(static::options());
    }

    public static function exists(string $code): bool
    {
        return in_array($code, static::codes(), true);
    }
}
