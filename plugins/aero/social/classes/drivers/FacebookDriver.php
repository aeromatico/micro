<?php namespace Aero\Social\Classes\Drivers;

use Aero\Social\Classes\MetaClient;
use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;

/** Páginas de Facebook: texto/enlace, fotos (1 o varias) o un video. */
class FacebookDriver implements DriverInterface
{
    public function validate(Post $post, array $media): array
    {
        $errors = [];
        $videos = array_filter($media, fn ($m) => $m['video']);

        if (!trim((string) $post->content) && !$media && !$post->link) {
            $errors[] = 'Facebook: escribe un texto, agrega un enlace o adjunta archivos.';
        }
        if ($videos && count($media) > 1) {
            $errors[] = 'Facebook: un video va solo; no se puede mezclar con fotos ni con otro video.';
        }
        if (count($media) > 10) {
            $errors[] = 'Facebook: máximo 10 fotos por publicación.';
        }

        return $errors;
    }

    public function publish(PostTarget $target, Post $post, Account $account, array $media): array
    {
        $page = $account->external_id;
        $token = $account->access_token;
        $message = trim((string) $post->content);

        // Un video.
        if ($media && $media[0]['video']) {
            $res = MetaClient::post("{$page}/videos", [
                'file_url'    => $media[0]['url'],
                'description' => $this->withLink($message, $post->link),
            ], $token, 120);

            return $this->done($res['id'] ?? null, $page);
        }

        // Una foto.
        if (count($media) === 1) {
            $res = MetaClient::post("{$page}/photos", [
                'url'     => $media[0]['url'],
                'caption' => $this->withLink($message, $post->link),
            ], $token, 60);

            return $this->done($res['post_id'] ?? $res['id'] ?? null, $page);
        }

        // Varias fotos: se suben sin publicar y se adjuntan a un post.
        $params = ['message' => $this->withLink($message, $post->link)];
        foreach ($media as $i => $m) {
            $photo = MetaClient::post("{$page}/photos", ['url' => $m['url'], 'published' => 'false'], $token, 60);
            $params["attached_media[{$i}]"] = json_encode(['media_fbid' => $photo['id']]);
        }

        // Solo texto/enlace: el enlace va aparte para que Facebook arme la vista previa.
        if (!$media) {
            $params = array_filter(['message' => $message, 'link' => $post->link]);
        }

        $res = MetaClient::post("{$page}/feed", $params, $token);

        return $this->done($res['id'] ?? null, $page);
    }

    public function check(PostTarget $target, Post $post, Account $account): array
    {
        // Facebook publica en una sola llamada; no hay estado intermedio.
        return $this->done($target->external_id, $account->external_id);
    }

    protected function withLink(string $message, ?string $link): string
    {
        return trim($message . ($link ? "\n\n" . $link : ''));
    }

    protected function done(?string $id, string $page): array
    {
        return [
            'status'      => 'published',
            'external_id' => $id,
            'permalink'   => $id ? 'https://www.facebook.com/' . $id : 'https://www.facebook.com/' . $page,
        ];
    }
}
