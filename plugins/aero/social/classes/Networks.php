<?php namespace Aero\Social\Classes;

/**
 * Catálogo de redes. Sumar una red = una entrada aquí + un driver en
 * classes/drivers + (si es OAuth2) un proveedor en classes/oauth + su acción
 * social.post.{red} en Créditos.
 */
class Networks
{
    public const ALL = [
        'facebook'  => ['label' => 'Facebook',  'color' => '#1877F2', 'driver' => Drivers\FacebookDriver::class,  'connect' => 'meta', 'available' => true],
        'instagram' => ['label' => 'Instagram', 'color' => '#E4405F', 'driver' => Drivers\InstagramDriver::class, 'connect' => 'meta', 'available' => true],
        'youtube'   => ['label' => 'YouTube',   'color' => '#FF0000', 'driver' => Drivers\YoutubeDriver::class, 'connect' => 'oauth', 'available' => true],
        'tiktok'    => ['label' => 'TikTok',    'color' => '#000000', 'driver' => Drivers\TikTokDriver::class,  'connect' => 'oauth', 'available' => true],
        'x'         => ['label' => 'X',         'color' => '#000000', 'driver' => Drivers\XDriver::class,       'connect' => 'oauth', 'available' => true],
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
