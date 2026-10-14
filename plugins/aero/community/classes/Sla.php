<?php namespace Aero\Community\Classes;

/**
 * Nuestro compromiso: responder en pocos minutos. La interfaz dice «pocos
 * minutos» a propósito (sin número) para poder ajustar este valor sin tocar
 * textos; el tablero de moderación y el cron vigilan lo que lo supera.
 */
class Sla
{
    public const MINUTES = 15;

    public const TYPES_WITH_COMMITMENT = ['idea', 'bug', 'help'];

    public static function dueFor(string $type): ?\Carbon\Carbon
    {
        return in_array($type, self::TYPES_WITH_COMMITMENT, true) ? now()->addMinutes(self::MINUTES) : null;
    }
}
