<?php namespace Aero\Workflows\Classes\Nodes;

use Aero\Workflows\Classes\SafeUrl;

/**
 * Nodo «Capturar ubicación»: extrae una coordenada (lat, lng) de lo que el
 * cliente comparta, para delivery, cobertura, rutas, etc. Acepta:
 *   - la ubicación nativa de WhatsApp (el body guardado por Hello/wapi:
 *     «📍 (-16.5, -68.15)» + nombre/dirección en la línea siguiente);
 *   - coordenadas escritas («-16.5000, -68.1500», «geo:-16.5,-68.15»);
 *   - enlaces de Google Maps, Apple Maps u OpenStreetMap (incluye los cortos
 *     maps.app.goo.gl: se resuelve UN salto, solo en esos hosts, vía SafeUrl);
 *   - campos explícitos `latitude` / `longitude` (webhook, herramienta de IA).
 *
 * Handles de salida: `found` (con ubicación) y `not_found` (sin ella), para
 * que el flujo pueda pedirle al cliente que la comparta.
 */
class LocationCapture
{
    public const DEFAULT_VAR = 'ubicacion';
    public const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl'];

    public static function handle(array $data, array $ctx): array
    {
        $found = static::fromExplicit($data) ?? static::fromText(static::sourceText($data, $ctx));

        if (!$found) {
            return [
                'output' => ['found' => false, 'reason' => 'No se encontró una ubicación válida en el mensaje.'],
                'handle' => 'not_found',
                'var'    => trim((string) ($data['save_as'] ?? '')) ?: static::DEFAULT_VAR,
            ];
        }

        $output = ['found' => true] + $found + [
            'coords'   => $found['lat'] . ',' . $found['lng'],
            'maps_url' => 'https://www.google.com/maps?q=' . $found['lat'] . ',' . $found['lng'],
        ];

        $zone = static::zone($data, $found['lat'], $found['lng']);

        if ($zone) {
            $output += $zone;
        }

        return [
            'output' => $output,
            'handle' => 'found',
            'var'    => trim((string) ($data['save_as'] ?? '')) ?: static::DEFAULT_VAR,
        ];
    }

    /** Texto a analizar: el campo «source»; si está vacío, el mensaje que disparó el flujo. */
    protected static function sourceText(array $data, array $ctx): string
    {
        $source = $data['source'] ?? '';

        if (is_scalar($source) && trim((string) $source) !== '') {
            return (string) $source;
        }

        $trigger = (array) ($ctx['trigger'] ?? []);

        foreach ([$trigger['data'][0]['body'] ?? null, $trigger['body'] ?? null, $trigger['text'] ?? null, $trigger['location'] ?? null] as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return (string) $candidate;
            }
        }

        return '';
    }

    protected static function fromExplicit(array $data): ?array
    {
        if (!isset($data['latitude'], $data['longitude']) || $data['latitude'] === '' || $data['longitude'] === '') {
            return null;
        }

        if (!is_numeric($data['latitude']) || !is_numeric($data['longitude'])) {
            return null;
        }

        return static::valid((float) $data['latitude'], (float) $data['longitude'], '', 'campos');
    }

    /** @return array{lat: float, lng: float, name: string, source: string}|null */
    public static function fromText(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        // 1) Enlaces: buscar coordenadas en cada URL del texto.
        if (preg_match_all('~https?://[^\s<>"\')]+~i', $text, $urls)) {
            foreach ($urls[0] as $url) {
                $pair = static::fromUrl($url) ?? static::fromUrl(static::resolveShortLink($url));

                if ($pair) {
                    return static::valid($pair[0], $pair[1], static::labelFrom($text, $url), 'enlace');
                }
            }
        }

        // 2) geo:lat,lng
        if (preg_match('/geo:\s*(-?\d{1,3}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/i', $text, $m)) {
            return static::valid((float) $m[1], (float) $m[2], static::labelFrom($text, $m[0]), 'texto');
        }

        // 3) Par suelto «lat, lng». En texto libre exigimos decimales en ambos
        //    números (y coma/punto y coma, o 4+ decimales si solo hay espacio)
        //    para no confundir teléfonos, precios o fechas con coordenadas.
        $n = '-?\d{1,3}\.\d+';

        if (preg_match("/({$n})\s*[,;]\s*({$n})/", $text, $m)
            || preg_match('/(-?\d{1,3}\.\d{4,})\s+(-?\d{1,3}\.\d{4,})/', $text, $m)) {
            return static::valid((float) $m[1], (float) $m[2], static::labelFrom($text, $m[0]), 'texto');
        }

        return null;
    }

    /** @return array{0: float, 1: float}|null */
    protected static function fromUrl(?string $url): ?array
    {
        if (!$url) {
            return null;
        }

        $url = urldecode($url);
        $n = '(-?\d{1,3}(?:\.\d+)?)';
        $patterns = [
            "/@{$n},\s*{$n}/",                                                     // google.com/maps/@lat,lng,17z · /place/x/@lat,lng
            "/!3d{$n}!4d{$n}/",                                                    // data=!3dLAT!4dLNG
            "/[?&](?:q|query|ll|center|destination|daddr|sll|saddr)=(?:loc:)?{$n}\s*,\s*{$n}/", // maps.google.com/?q=lat,lng
            "/[?&]mlat={$n}&mlon={$n}/",                                           // openstreetmap
            "~#map=\d+/{$n}/{$n}~",                                                // openstreetmap #map=z/lat/lng
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m) && abs((float) $m[1]) <= 90 && abs((float) $m[2]) <= 180) {
                return [(float) $m[1], (float) $m[2]];
            }
        }

        return null;
    }

    /** maps.app.goo.gl y similares redirigen: leemos UN Location (sin seguirlo a ciegas). */
    protected static function resolveShortLink(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (!in_array($host, static::SHORT_HOSTS, true)) {
            return null;
        }

        $location = null;

        for ($hop = 0; $hop < 2; $hop++) {
            $response = SafeUrl::request('GET', $url);
            $location = $response['headers']['Location'][0] ?? $response['headers']['location'][0] ?? null;

            if (!$location) {
                return null;
            }

            if (static::fromUrl($location)) {
                return $location;
            }

            // Segundo salto solo si sigue siendo un host de enlaces cortos o de Google Maps.
            $nextHost = strtolower((string) parse_url($location, PHP_URL_HOST));

            if (!in_array($nextHost, array_merge(static::SHORT_HOSTS, ['maps.google.com', 'www.google.com', 'google.com']), true)) {
                return null;
            }

            $url = $location;
        }

        return $location;
    }

    protected static function valid(float $lat, float $lng, string $name, string $source): ?array
    {
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return null;
        }

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6), 'name' => $name, 'source' => $source];
    }

    /** Nombre/dirección: lo que quede del mensaje sin la coordenada, el emoji ni los enlaces. */
    protected static function labelFrom(string $text, string $token): string
    {
        $label = str_replace($token, ' ', $text);
        $label = preg_replace('~https?://\S+~i', ' ', $label);
        $label = preg_replace('/📍|\(\s*\)/u', ' ', $label);
        $label = trim(preg_replace('/\s+/u', ' ', $label));

        return mb_substr($label, 0, 200);
    }

    /**
     * Cobertura opcional: si hay centro y radio, calcula la distancia (km) y
     * si la coordenada cae dentro. Útil para decidir si se hace el delivery.
     */
    protected static function zone(array $data, float $lat, float $lng): ?array
    {
        $cLat = $data['center_lat'] ?? null;
        $cLng = $data['center_lng'] ?? null;
        $radius = $data['radius_km'] ?? null;

        if (!is_numeric($cLat) || !is_numeric($cLng) || !is_numeric($radius) || (float) $radius <= 0) {
            return null;
        }

        $distance = static::distanceKm($lat, $lng, (float) $cLat, (float) $cLng);

        return ['distance_km' => round($distance, 2), 'in_zone' => $distance <= (float) $radius];
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0088;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
