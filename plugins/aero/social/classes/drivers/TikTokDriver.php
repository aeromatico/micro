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
 * TikTok (Content Posting API, publicación directa). Un video (se sube desde
 * el servidor por trozos) o un carrusel de fotos JPG (TikTok las descarga de
 * su URL: el dominio debe estar verificado en la app de TikTok). Hasta que
 * TikTok audite la app, todo sale en privado (SELF_ONLY).
 */
class TikTokDriver implements DriverInterface
{
    public const API = 'https://open.tiktokapis.com/v2';
    public const CHUNK = 10 * 1000 * 1000;      // entre 5 y 64 MB
    public const MIN_CHUNK = 5 * 1000 * 1000;

    public function validate(Post $post, array $media): array
    {
        $videos = array_filter($media, fn ($m) => $m['video']);
        $errors = [];

        if (!$media) {
            $errors[] = 'TikTok: adjunta un video o fotos.';
        } elseif ($videos && count($media) > 1) {
            $errors[] = 'TikTok: un video va solo, sin fotos.';
        } elseif (!$videos) {
            if (count($media) > 35) {
                $errors[] = 'TikTok: máximo 35 fotos.';
            }
            foreach ($media as $m) {
                if (!in_array($m['ext'], ['jpg', 'jpeg'], true)) {
                    $errors[] = "TikTok: las fotos deben ser JPG («{$m['name']}» es {$m['ext']}).";
                }
            }
        }
        if (mb_strlen($this->caption($post)) > 2200) {
            $errors[] = 'TikTok: el texto pasa de 2.200 caracteres.';
        }

        return $errors;
    }

    public function publish(PostTarget $target, Post $post, Account $account, array $media): array
    {
        $token = Tokens::fresh($account);
        $privacy = $this->privacy($token);
        $settings = ['privacy_level' => $privacy, 'disable_comment' => false];

        if ($media[0]['video']) {
            $m = $media[0];
            [$chunk, $count] = self::chunks($m['size']);

            $init = $this->call(fn () => Http::client()->withToken($token)->post(self::API . '/post/publish/video/init/', [
                'post_info'   => ['title' => $this->caption($post), 'disable_duet' => false, 'disable_stitch' => false] + $settings,
                'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $m['size'], 'chunk_size' => $chunk, 'total_chunk_count' => $count],
            ]));

            $this->upload((string) $init->json('data.upload_url'), $m, $chunk, $count, $target);
            $publishId = (string) $init->json('data.publish_id');
        } else {
            $init = $this->call(fn () => Http::client()->withToken($token)->post(self::API . '/post/publish/content/init/', [
                'post_info'   => ['title' => mb_substr($this->firstLine($post), 0, 90), 'description' => mb_substr($this->caption($post), 0, 4000)] + $settings,
                'source_info' => ['source' => 'PULL_FROM_URL', 'photo_cover_index' => 0, 'photo_images' => array_column($media, 'url')],
                'post_mode'   => 'DIRECT_POST',
                'media_type'  => 'PHOTO',
            ]));
            $publishId = (string) $init->json('data.publish_id');
        }

        return ['status' => 'processing', 'state' => ['publish_id' => $publishId, 'privacy' => $privacy], 'delay' => 15];
    }

    public function check(PostTarget $target, Post $post, Account $account): array
    {
        $state = (array) $target->state;
        $token = Tokens::fresh($account);

        $res = $this->call(fn () => Http::client()->withToken($token)
            ->post(self::API . '/post/publish/status/fetch/', ['publish_id' => $state['publish_id'] ?? '']));
        $status = $res->json('data.status');

        if ($status === 'FAILED') {
            throw new SocialException('TikTok rechazó la publicación: ' . ($res->json('data.fail_reason') ?: 'motivo no informado') . '.');
        }
        if ($status !== 'PUBLISH_COMPLETE') {
            return ['status' => 'processing', 'state' => $state, 'delay' => 15];
        }

        $id = $res->json('data.publicaly_available_post_id.0');   // así lo escribe TikTok

        return [
            'status'      => 'published',
            'external_id' => $id ? (string) $id : ($state['publish_id'] ?? null),
            // Privado (SELF_ONLY) no tiene enlace público.
            'permalink'   => $id ? 'https://www.tiktok.com/video/' . $id : null,
        ];
    }

    /** @return array{0:int, 1:int} tamaño de trozo y cantidad (el último absorbe el resto). */
    public static function chunks(int $size): array
    {
        if ($size <= self::MIN_CHUNK) {
            return [$size, 1];
        }

        $chunk = min(self::CHUNK, $size);

        return [$chunk, max(1, intdiv($size, $chunk))];
    }

    protected function upload(string $url, array $m, int $chunk, int $count, PostTarget $target): void
    {
        $stream = Media::stream($m);
        $offset = 0;
        try {
            for ($i = 0; $i < $count; $i++) {
                $length = $i === $count - 1 ? $m['size'] - $offset : $chunk;
                $bytes = Media::read($stream, $length);
                $end = $offset + strlen($bytes) - 1;

                $res = Http::run('TikTok', fn () => Http::client(300)
                    ->withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$m['size']}"])
                    ->withBody($bytes, $m['mime'] ?: 'video/mp4')
                    ->put($url));
                $target->touch();

                if (!$res->successful()) {
                    throw new SocialException('TikTok: falló la subida del video (HTTP ' . $res->status() . ').');
                }
                $offset = $end + 1;
            }
        } finally {
            fclose($stream);
        }
    }

    /** Público solo si la app está auditada y la cuenta lo permite; si no, privado. */
    protected function privacy(string $token): string
    {
        $res = $this->call(fn () => Http::client()->withToken($token)->post(self::API . '/post/publish/creator_info/query/', (object) []));
        $options = (array) $res->json('data.privacy_level_options');

        if (config('aero.social::tiktok.audited') && in_array('PUBLIC_TO_EVERYONE', $options, true)) {
            return 'PUBLIC_TO_EVERYONE';
        }

        return in_array('SELF_ONLY', $options, true) || !$options ? 'SELF_ONLY' : $options[0];
    }

    protected function call(\Closure $request): Response
    {
        $res = Http::run('TikTok', $request);
        $code = $res->json('error.code');

        if ($res->successful() && (!$code || $code === 'ok')) {
            return $res;
        }

        $message = $res->json('error.message') ?: ('HTTP ' . $res->status());

        throw match (true) {
            in_array($code, ['access_token_invalid', 'scope_not_authorized', 'token_not_authorized_for_specified_open_id'], true), $res->status() === 401
                => SocialException::tokenInvalid('TikTok rechazó la conexión. Vuelve a conectar la cuenta.'),
            $code === 'spam_risk_too_many_posts' => new SocialException('TikTok: la cuenta llegó a su límite de publicaciones del día.'),
            $code === 'unaudited_client_can_only_post_to_private_accounts' => new SocialException('TikTok: mientras la app no esté auditada, la cuenta debe ser privada para publicar.'),
            $code === 'url_ownership_unverified' => new SocialException('TikTok: para publicar fotos, el dominio de los archivos debe estar verificado en la app de TikTok.'),
            default => new SocialException('TikTok: ' . $message),
        };
    }

    protected function caption(Post $post): string
    {
        return trim(trim((string) $post->content) . ($post->link ? "\n\n" . $post->link : ''));
    }

    protected function firstLine(Post $post): string
    {
        return trim(strtok(trim((string) $post->content), "\n") ?: '');
    }
}
