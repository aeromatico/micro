<?php namespace Aero\Social\Classes;

/**
 * Catálogo de redes. Sumar una red = una entrada aquí + un driver en
 * classes/drivers + su acción social.post.{red} en Créditos.
 */
class Networks
{
    public const ALL = [
        'facebook'  => ['label' => 'Facebook',  'color' => '#1877F2', 'driver' => Drivers\FacebookDriver::class,  'connect' => 'meta', 'available' => true],
        'instagram' => ['label' => 'Instagram', 'color' => '#E4405F', 'driver' => Drivers\InstagramDriver::class, 'connect' => 'meta', 'available' => true],
        'tiktok'    => ['label' => 'TikTok',    'color' => '#000000', 'driver' => null, 'connect' => null, 'available' => false],
        'youtube'   => ['label' => 'YouTube',   'color' => '#FF0000', 'driver' => null, 'connect' => null, 'available' => false],
        'x'         => ['label' => 'X',         'color' => '#000000', 'driver' => null, 'connect' => null, 'available' => false],
    ];

    public static function label(string $code): string
    {
        return self::ALL[$code]['label'] ?? ucfirst($code);
    }

    public static function driver(string $code): Drivers\DriverInterface
    {
        $class = self::ALL[$code]['driver'] ?? null;
        if (!$class) {
            throw new SocialException('La red «' . self::label($code) . '» todavía no está disponible.');
        }

        return new $class();
    }
}
