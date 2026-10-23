<?php namespace Aero\Church\Classes;

/**
 * Convierte el enlace de un video en una URL incrustable SOLO para proveedores conocidos (nunca un
 * iframe a un sitio arbitrario). Otros enlaces https se muestran como vínculo; el resto, nada.
 */
class Embed
{
    /** URL para el iframe, o null si el proveedor no es de confianza. */
    public static function src(?string $url): ?string
    {
        $url = trim((string) $url);
        if (!self::isHttps($url)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.)/', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        if (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            $id = $q['v'] ?? (preg_match('~^/(?:live|embed|shorts)/([\w-]{6,20})~', $path, $m) ? $m[1] : null);

            return $id && preg_match('/^[\w-]{6,20}$/', $id) ? 'https://www.youtube-nocookie.com/embed/' . $id : null;
        }
        if ($host === 'youtu.be') {
            $id = trim($path, '/');

            return preg_match('/^[\w-]{6,20}$/', $id) ? 'https://www.youtube-nocookie.com/embed/' . $id : null;
        }
        if (in_array($host, ['facebook.com', 'fb.watch'], true)) {
            return 'https://www.facebook.com/plugins/video.php?show_text=false&href=' . rawurlencode($url);
        }
        if ($host === 'vimeo.com' && preg_match('~^/(\d{5,12})~', $path, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }

    public static function isHttps(?string $url): bool
    {
        return (bool) preg_match('~^https://[^\s/]+~i', trim((string) $url)) && filter_var(trim((string) $url), FILTER_VALIDATE_URL) !== false;
    }
}
