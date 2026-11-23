<?php namespace Aero\Social\Classes\Drivers;

use Aero\Social\Classes\Media;
use Aero\Social\Classes\Oauth\Http;
use Aero\Social\Classes\SocialException;
use Aero\Social\Classes\Tokens;
use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;

/**
 * YouTube: un video por publicación, subida reanudable por trozos (el archivo
 * nunca entra entero en memoria). Título = primera línea del texto. Vertical
 * y de 60 s o menos = Short. Cuota: ~1.600 de 10.000 unidades diarias por subida.
 */
class YoutubeDriver implements DriverInterface
{
    public const CHUNK = 8 * 1024 * 1024; // múltiplo de 256 KiB

    public function validate(Post $post, array $media): array
    {
        $videos = array_filter($media, fn ($m) => $m['video']);

        if (count($videos) !== 1 || count($media) !== 1) {
            return ['YouTube: adjunta exactamente un video (sin fotos).'];
        }
        if (!trim((string) $post->content)) {
            return ['YouTube: escribe un texto; la primera línea será el título del video.'];
        }

        return [];
    }

    public function publish(PostTarget $target, Post $post, Account $account, array $media): array
    {
        $m = $media[0];
        $token = Tokens::fresh($account);
        $mime = $m['mime'] ?: 'video/mp4';

        $metadata = [
            'snippet' => ['title' => $this->title($post), 'description' => $this->description($post), 'categoryId' => '22'],
            'status'  => ['privacyStatus' => config('aero.social::youtube.privacy', 'public'), 'selfDeclaredMadeForKids' => false],
        ];

        $init = Http::run('YouTube', fn () => Http::client(30)->withToken($token)
            ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $m['size']])
            ->withOptions(['allow_redirects' => false])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', $metadata));

        if ($init->failed() || !$init->header('Location')) {
            throw $this->error($init->json('error') ?? [], $init->status());
        }

        $location = $init->header('Location');
        $stream = Media::stream($m);
        $offset = 0;

        try {
            while ($offset < $m['size']) {
                $chunk = Media::read($stream, self::CHUNK);
                if ($chunk === '') {
                    break;
                }
                $end = $offset + strlen($chunk) - 1;

                $res = Http::run('YouTube', fn () => Http::client(300)->withToken($token)
                    ->withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$m['size']}"])
                    ->withBody($chunk, $mime)
                    ->withOptions(['allow_redirects' => false])
                    ->put($location));

                $target->touch();   // latido: el barrido no la da por colgada

                if ($res->status() === 308) {
                    $offset = $end + 1;
                    continue;
                }
                if ($res->successful() && $res->json('id')) {
                    $id = (string) $res->json('id');

                    return ['status' => 'published', 'external_id' => $id, 'permalink' => 'https://www.youtube.com/watch?v=' . $id];
                }

                throw $this->error($res->json('error') ?? [], $res->status());
            }
        } finally {
            fclose($stream);
        }

        throw new SocialException('YouTube no confirmó la subida.');
    }

    public function check(PostTarget $target, Post $post, Account $account): array
    {
        throw new SocialException('YouTube: estado desconocido; vuelve a intentarlo.');
    }

    protected function title(Post $post): string
    {
        $first = trim(strtok(trim((string) $post->content), "\n") ?: '');

        return mb_substr(str_replace(['<', '>'], '', $first), 0, 100) ?: 'Video';
    }

    protected function description(Post $post): string
    {
        return mb_substr(str_replace(['<', '>'], '', trim(trim((string) $post->content) . ($post->link ? "\n\n" . $post->link : ''))), 0, 5000);
    }

    protected function error(array $error, int $status): SocialException
    {
        $reason = $error['errors'][0]['reason'] ?? null;

        if ($status === 401 || in_array($reason, ['authError', 'insufficientPermissions', 'forbidden'], true)) {
            return SocialException::tokenInvalid('YouTube rechazó la conexión. Vuelve a conectar el canal.');
        }

        return new SocialException(match ($reason) {
            'quotaExceeded', 'uploadLimitExceeded' => 'YouTube: se agotó la cuota diaria de subidas; reintenta mañana.',
            'youtubeSignupRequired' => 'YouTube: la cuenta no tiene canal.',
            default => 'YouTube: ' . ($error['message'] ?? "error {$status}."),
        });
    }
}
