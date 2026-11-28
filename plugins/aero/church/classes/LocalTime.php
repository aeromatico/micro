<?php namespace Aero\Church\Classes;

use Carbon\Carbon;

/**
 * Las fechas con hora se guardan en UTC (el selector del panel ya las convierte); todo lo que lee una
 * persona (sitio, mensajes, reportes) debe pasar por aquí para mostrarse en la hora de la iglesia.
 */
class LocalTime
{
    public static function zone(): string
    {
        return (string) config('cms.timezone', 'America/La_Paz');
    }

    public static function of(?\DateTimeInterface $value): ?Carbon
    {
        return $value ? Carbon::instance($value)->setTimezone(self::zone()) : null;
    }

    /** «El día X a las HH:MM» de la iglesia, convertido a UTC para guardarlo. */
    public static function toUtc(\DateTimeInterface $day, int $hour, int $minute = 0): Carbon
    {
        return Carbon::parse($day->format('Y-m-d'), self::zone())->setTime($hour, $minute)->utc();
    }
}
