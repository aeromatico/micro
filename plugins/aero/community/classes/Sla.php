<?php namespace Aero\Community\Classes;

/**
 * Nuestro compromiso: toda sugerencia, error o ayuda recibe respuesta humana
 * dentro de este plazo. El tablero de moderación y el cron lo vigilan.
 */
class Sla
{
    public const DAYS = 7;

    public const TYPES_WITH_COMMITMENT = ['idea', 'bug', 'help'];

    public static function dueFor(string $type): ?\Carbon\Carbon
    {
        return in_array($type, self::TYPES_WITH_COMMITMENT, true) ? now()->addDays(self::DAYS) : null;
    }
}
