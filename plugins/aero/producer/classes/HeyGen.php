<?php namespace Aero\Producer\Classes;

use Aero\Producer\Models\Credential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente mínimo de HeyGen: la llave sale siempre de la base de datos (cifrada), nunca del repo. */
class HeyGen
{
    public const BASE = 'https://api.heygen.com';

    public function __construct(protected string $key)
    {
    }

    public static function platform(): ?self
    {
        $key = Credential::whereNull('tenant_id')->where('provider', 'heygen')->where('is_active', true)
            ->orderByDesc('id')->first()?->plainKey();

        return $key ? new self($key) : null;
    }

    protected function http(int $timeout = 30): PendingRequest
    {
        return Http::timeout($timeout)->withOptions(['force_ip_resolve' => 'v4'])->withHeaders(['X-Api-Key' => $this->key]);
    }

    protected function check($response): array
    {
        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? $response->json('message') ?? ('HeyGen respondió ' . $response->status() . '.'));
        }

        return (array) $response->json('data', []);
    }

    /** Saldo en dólares de la cuenta; sirve también para probar la llave. */
    public function balance(): float
    {
        return (float) ($this->check($this->http(15)->get(self::BASE . '/v3/users/me'))['wallet']['remaining_balance'] ?? 0);
    }

    /** Sube un archivo local (imagen o audio) y devuelve su asset_id. */
    public function upload(string $path, string $mime): string
    {
        $response = $this->http(120)->attach('file', file_get_contents($path), basename($path), ['Content-Type' => $mime])
            ->post(self::BASE . '/v3/assets');

        return (string) ($this->check($response)['asset_id'] ?? throw new RuntimeException('HeyGen no devolvió el archivo subido.'));
    }

    /** Crea el video y devuelve su id. */
    public function createVideo(array $body): string
    {
        $data = $this->check($this->http(60)->post(self::BASE . '/v3/videos', $body));

        return (string) ($data['video_id'] ?? $data['id'] ?? throw new RuntimeException('HeyGen no devolvió el id del video.'));
    }

    public function video(string $id): array
    {
        return $this->check($this->http(30)->get(self::BASE . '/v3/videos/' . $id));
    }

    public function download(string $url): string
    {
        $response = Http::timeout(180)->withOptions(['force_ip_resolve' => 'v4'])->get($url);
        if ($response->failed()) {
            throw new RuntimeException('No se pudo descargar el video terminado.');
        }

        return $response->body();
    }
}
