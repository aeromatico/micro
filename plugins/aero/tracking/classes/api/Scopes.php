<?php namespace Aero\Tracking\Classes\Api;

class Scopes
{
    public const ASSETS_READ   = 'tracking.assets.read';
    public const ASSETS_WRITE  = 'tracking.assets.write';
    public const JOBS_READ     = 'tracking.jobs.read';
    public const JOBS_WRITE    = 'tracking.jobs.write';
    public const TRACKING_READ  = 'tracking.positions.read';
    public const TRACKING_WRITE = 'tracking.positions.write';

    public static function all(): array
    {
        return [
            self::ASSETS_READ    => 'Consultar activos (vehículos, personas)',
            self::ASSETS_WRITE   => 'Crear, editar y eliminar activos',
            self::JOBS_READ      => 'Consultar trabajos y paradas',
            self::JOBS_WRITE     => 'Crear trabajos, asignarlos y cambiar su estado',
            self::TRACKING_READ  => 'Ver posiciones en vivo e historial de recorridos',
            self::TRACKING_WRITE => 'Enviar posiciones de un activo por API',
        ];
    }
}
