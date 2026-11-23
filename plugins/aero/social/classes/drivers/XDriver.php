<?php namespace Aero\Social\Classes\Drivers;

use Aero\Social\Classes\Media;
use Aero\Social\Classes\Oauth\Http;
use Aero\Social\Classes\SocialException;
use Aero\Social\Classes\Tokens;
use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;
use Illuminate\Http\Client\Response;

/**
 * X: texto de hasta 280 caracteres (cada enlace cuenta 23) con hasta 4 fotos o
 * un video/GIF. Los videos se suben por trozos y X los procesa antes de poder
 * adjuntarlos: eso se espera sin bloquear al worker.
 */
class XDriver implements DriverInterface
{
    public const API = 'https://api.x.com/2';
    public const MAX = 280;
    public const CHUNK = 4 * 1024 * 1024;

    public function validate(Post $post, array $media): array
    {
        $errors = [];
        $animated = array_filter($media, fn ($m) => $m['video'] || $m['ext'] === 'gif');

        if (!trim((string) $post->content) && !$media) {
            $errors[] = 'X: escribe un texto o adjunta archivos.';
        }
        if ($animated && count($media) > 1) {
            $errors[] = 'X: un video o GIF va solo, sin otras fotos.';
        }
        if (count($media) > 4) {
            $errors[] = 'X: máximo 4 fotos por publicación.';
        }
        if (($len = self::length($this->text($post))) > self::MAX) {
            $errors[] = "X: el texto tiene {$len} caracteres (máximo 280; cada enlace cuenta 23).";
        }

        return $errors;
    }

    /** Largo como lo cuenta X (aprox.): cada URL = 23. */
    public static function length(string $text): int
    {
        $urls = preg_match_all('~https?://\S+~u', $text, $m);
        $rest = preg_replace('~https?://\S+~u', '', $text);

        return mb_strlen($rest) + $urls * 23;
    }

    public function publish(PostTarget $target, Post $post, Account $account, array $media): array
    {
        $token = Tokens::fresh($account);
        $ids = [];
        $pending = [];

        foreach ($media as $m) {
            if ($m['video'] || $m['ext'] === 'gif') {
                [$id, $processing] = $this->uploadChunked($token, $m, $target);
                $ids[] = $id;
                if ($processing) {
                    $pending[] = $id;
                }
            } else {
                $ids[] = $this->uploadImage($token, $m);
            }
        }

        if ($pending) {
            return ['status' => 'processing', 'state' => ['media_ids' => $ids, 'pending' => $pending], 'delay' => 10];
        }

        return $this->tweet($token, $post, $account, $ids);
    }

    public function check(PostTarget $target, Post $post, Account $account): array
    {
        $token = Tokens::fresh($account);
        $state = (array) $target->state;

        foreach ((array) ($state['pending'] ?? []) as $id) {
            $res = $this->call(fn () => Http::client()->withToken($token)
                ->get(self::API . '/media/upload', ['media_id' => $id, 'command' => 'STATUS']));
            $info = $res->json('data.processing_info') ?? [];

            if (($info['state'] ?? 'succeeded') === 'failed') {
                throw new SocialException('X no pudo procesar el video: ' . ($info['error']['message'] ?? 'formato no admitido') . '.');
            }
            if (($info['state'] ?? 'succeeded') !== 'succeeded') {
                return ['status' => 'processing', 'state' => $state, 'delay' => max(5, (int) ($info['check_after_secs'] ?? 10))];
            }
        }

        return $this->tweet($token, $post, $account, (array) ($state['media_ids'] ?? []));
    }

    protected function tweet(string $token, Post $post, Account $account, array $mediaIds): array
    {
        $body = ['text' => $this->text($post)];
        if ($mediaIds) {
            $body['media'] = ['media_ids' => array_values(array_map('strval', $mediaIds))];
        }

        $res = $this->call(fn () => Http::client()->withToken($token)->post(self::API . '/tweets', $body));
        $id = (string) $res->json('data.id');

        return [
            'status'      => 'published',
            'external_id' => $id,
            'permalink'   => 'https://x.com/' . ($account->username ?: 'i/web') . '/status/' . $id,
        ];
    }

    protected function uploadImage(string $token, array $m): string
    {
        $stream = Media::stream($m);
        try {
            $res = $this->call(fn () => Http::client(60)->withToken($token)
                ->attach('media', $stream, $m['name'])
                ->post(self::API . '/media/upload', ['media_category' => 'tweet_image', 'media_type' => $m['mime'] ?: 'image/jpeg']));
        } finally {
            is_resource($stream) && fclose($stream);
        }

        return (string) $res->json('data.id');
    }

    /** @return array{0:string, 1:bool} id y si X aún lo está procesando */
    protected function uploadChunked(string $token, array $m, PostTarget $target): array
    {
        $isGif = $m['ext'] === 'gif';
        $init = $this->call(fn () => Http::client()->withToken($token)->post(self::API . '/media/upload/initialize', [
            'media_type'     => $isGif ? 'image/gif' : ($m['mime'] ?: 'video/mp4'),
            'total_bytes'    => $m['size'],
            'media_category' => $isGif ? 'tweet_gif' : 'tweet_video',
        ]));
        $id = (string) $init->json('data.id');

        $stream = Media::stream($m);
        try {
            for ($i = 0; ($chunk = Media::read($stream, self::CHUNK)) !== ''; $i++) {
                $this->call(fn () => Http::client(120)->withToken($token)
                    ->attach('media', $chunk, 'chunk')
                    ->post(self::API . "/media/upload/{$id}/append", ['segment_index' => $i]));
                $target->touch();
            }
        } finally {
            fclose($stream);
        }

        $final = $this->call(fn () => Http::client(60)->withToken($token)->post(self::API . "/media/upload/{$id}/finalize"));
        $state = $final->json('data.processing_info.state');

        if ($state === 'failed') {
            throw new SocialException('X no pudo procesar el archivo.');
        }

        return [$id, $state !== null && $state !== 'succeeded'];
    }

    protected function call(\Closure $request): Response
    {
        $res = Http::run('X', $request);
        if ($res->successful()) {
            return $res;
        }

        $detail = $res->json('detail') ?? $res->json('errors.0.message') ?? $res->json('title') ?? ('HTTP ' . $res->status());

        throw match (true) {
            $res->status() === 401 => SocialException::tokenInvalid('X rechazó la conexión. Vuelve a conectar la cuenta.'),
            $res->status() === 429 => new SocialException('X: se alcanzó el límite de publicaciones de la API por ahora. Reintenta más tarde.'),
            default => new SocialException('X: ' . $detail),
        };
    }

    protected function text(Post $post): string
    {
        return trim(trim((string) $post->content) . ($post->link ? "\n\n" . $post->link : ''));
    }
}
