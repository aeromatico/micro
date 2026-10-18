<?php namespace Aero\Producer\Classes;

use Aero\Producer\Models\Credential;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente mínimo de ElevenLabs: la llave sale siempre de la base de datos (cifrada), nunca del repo. */
class ElevenLabs
{
    public const BASE = 'https://api.elevenlabs.io';

    public function __construct(protected string $key)
    {
    }

    /** Llave activa de la plataforma; los tenants usan esta misma a través de nuestro servicio (créditos). */
    public static function platform(): ?self
    {
        $key = Credential::whereNull('tenant_id')->where('provider', 'elevenlabs')->where('is_active', true)
            ->orderByDesc('id')->first()?->plainKey();

        return $key ? new self($key) : null;
    }

    public function voices(int $limit = 100): array
    {
        $response = Http::timeout(20)->withOptions(['force_ip_resolve' => 'v4'])
            ->withHeaders(['xi-api-key' => $this->key])->get(self::BASE . '/v2/voices', ['page_size' => $limit]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('detail.message') ?? ('ElevenLabs respondió ' . $response->status() . '.'));
        }

        return collect($response->json('voices', []))->map(fn ($v) => [
            'id' => $v['voice_id'], 'name' => $v['name'], 'category' => $v['category'] ?? null,
        ])->all();
    }

    public function test(): int
    {
        return count($this->voices(1));
    }
}
