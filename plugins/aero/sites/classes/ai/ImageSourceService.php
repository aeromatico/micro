<?php namespace Aero\Sites\Classes\Ai;

use Aero\Sites\Models\ImageCache;
use Aero\Sites\Models\Settings as SitesSettings;
use Http;
use Log;
use Carbon\Carbon;

/**
 * Resuelve palabras clave (ej. "restaurante italiano interior") a una
 * fotografía real vía la API de Unsplash, con cache en BD para no repetir
 * búsquedas ni agotar el rate limit gratuito (50 req/hora).
 *
 * Nunca lanza excepción: si no hay key configurada o la API falla, retorna
 * un placeholder — la generación de sitio nunca debe romperse por esto.
 */
class ImageSourceService
{
    protected const API_URL = 'https://api.unsplash.com/search/photos';

    /**
     * @return array{url: string, attribution: ?string}
     */
    public function resolve(string $keywords): array
    {
        $keywords = trim($keywords);
        if ($keywords === '') {
            return $this->placeholder('Imagen');
        }

        $hash   = ImageCache::normalizeHash($keywords);
        $cached = ImageCache::where('keywords_hash', $hash)->first();

        if ($cached && $this->isFresh($cached)) {
            return ['url' => $cached->url, 'attribution' => $cached->attribution];
        }

        $resolved = $this->searchUnsplash($keywords);

        if (!$resolved) {
            // Si falló la API pero había un valor cacheado vencido, mejor
            // reusarlo que mostrar un placeholder.
            if ($cached) {
                return ['url' => $cached->url, 'attribution' => $cached->attribution];
            }
            return $this->placeholder($keywords);
        }

        ImageCache::updateOrCreate(
            ['keywords_hash' => $hash],
            [
                'keywords'    => mb_substr($keywords, 0, 190),
                'url'         => $resolved['url'],
                'attribution' => $resolved['attribution'],
                'provider'    => 'unsplash',
            ]
        );

        return $resolved;
    }

    protected function isFresh(ImageCache $cached): bool
    {
        try {
            $ttlDays = SitesSettings::getImageCacheTtlDays();
        } catch (\Exception $e) {
            $ttlDays = 30;
        }

        return $cached->updated_at && $cached->updated_at->gt(Carbon::now()->subDays($ttlDays));
    }

    /**
     * @return array{url: string, attribution: string}|null
     */
    protected function searchUnsplash(string $keywords): ?array
    {
        try {
            $accessKey = SitesSettings::getUnsplashAccessKey();
            if (!$accessKey) {
                return null;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Client-ID ' . $accessKey,
                'Accept-Version' => 'v1',
            ])->timeout(8)->get(self::API_URL, [
                'query'        => $keywords,
                'per_page'     => 1,
                'orientation'  => 'landscape',
                'content_filter' => 'high',
            ]);

            if (!$response->successful()) {
                Log::warning('Aero\\Sites\\ImageSourceService: Unsplash HTTP ' . $response->status());
                return null;
            }

            $photo = $response->json('results.0');
            if (!$photo) {
                return null;
            }

            $url = $photo['urls']['regular'] ?? $photo['urls']['small'] ?? null;
            if (!$url) {
                return null;
            }

            $author = $photo['user']['name'] ?? 'Unsplash';

            return [
                'url'         => $url,
                'attribution' => "Foto de {$author} en Unsplash",
            ];
        } catch (\Exception $e) {
            Log::warning('Aero\\Sites\\ImageSourceService: ' . $e->getMessage());
            return null;
        }
    }

    protected function placeholder(string $text): array
    {
        $label = rawurlencode(mb_substr($text, 0, 30));
        return [
            'url'         => "https://placehold.co/1200x800/e2e8f0/94a3b8?text={$label}",
            'attribution' => null,
        ];
    }
}
