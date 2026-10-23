<?php namespace Aero\Church\Classes;

/** QR en SVG (BaconQrCode) para enlaces públicos de la iglesia. */
class QrSvg
{
    public static function make(string $text, int $size = 200): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );

        return (new \BaconQrCode\Writer($renderer))->writeString($text);
    }
}
