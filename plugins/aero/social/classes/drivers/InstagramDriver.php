<?php namespace Aero\Social\Classes\Drivers;

use Aero\Social\Classes\MetaClient;
use Aero\Social\Classes\SocialException;
use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;

/**
 * Instagram (cuentas profesionales vinculadas a una página). Publicar es en
 * dos pasos: crear el contenedor, esperar a que Instagram lo procese y
 * publicarlo. La espera no bloquea al worker: el job se reprograma.
 */
class InstagramDriver implements DriverInterface
{
    public const CAPTION_MAX = 2200;
    public const DELAY = 10;

    public function validate(Post $post, array $media): array
    {
        $errors = [];

        if (!$media) {
            $errors[] = 'Instagram: necesita al menos una foto o un video.';
        }
        if (count($media) > 10) {
            $errors[] = 'Instagram: máximo 10 archivos por carrusel.';
        }
        foreach ($media as $m) {
            if (!$m['video'] && !in_array($m['ext'], ['jpg', 'jpeg'], true)) {
                $errors[] = "Instagram: solo acepta fotos JPG («{$m['name']}» es {$m['ext']}).";
            }
        }
        if (mb_strlen($this->caption($post)) > self::CAPTION_MAX) {
            $errors[] = 'Instagram: el texto pasa de 2.200 caracteres.';
        }

        return $errors;
    }

    public function publish(PostTarget $target, Post $post, Account $account, array $media): array
    {
        $ig = $account->external_id;
        $token = $account->access_token;
        $caption = $this->caption($post);

        if (count($media) === 1) {
            $m = $media[0];
            $params = $m['video']
                ? ['media_type' => 'REELS', 'video_url' => $m['url'], 'caption' => $caption]
                : ['image_url' => $m['url'], 'caption' => $caption];
            $res = MetaClient::post("{$ig}/media", $params, $token, 60);

            return $this->wait(['stage' => 'container', 'container' => $res['id']]);
        }

        // Carrusel: primero cada hijo; el contenedor padre se crea cuando todos estén listos.
        $children = [];
        foreach ($media as $m) {
            $params = $m['video']
                ? ['media_type' => 'VIDEO', 'video_url' => $m['url'], 'is_carousel_item' => 'true']
                : ['image_url' => $m['url'], 'is_carousel_item' => 'true'];
            $children[] = MetaClient::post("{$ig}/media", $params, $token, 60)['id'];
        }

        return $this->wait(['stage' => 'children', 'children' => $children]);
    }

    public function check(PostTarget $target, Post $post, Account $account): array
    {
        $state = (array) $target->state;
        $token = $account->access_token;
        $ig = $account->external_id;

        if (($state['stage'] ?? null) === 'children') {
            foreach ($state['children'] as $child) {
                if (!$this->ready($child, $token)) {
                    return $this->wait($state);
                }
            }

            $res = MetaClient::post("{$ig}/media", [
                'media_type' => 'CAROUSEL',
                'children'   => implode(',', $state['children']),
                'caption'    => $this->caption($post),
            ], $token);

            return $this->wait(['stage' => 'container', 'container' => $res['id']]);
        }

        if (($state['stage'] ?? null) !== 'container' || empty($state['container'])) {
            throw new SocialException('Instagram: la publicación quedó en un estado desconocido; vuelve a intentarlo.');
        }

        if (!$this->ready($state['container'], $token)) {
            return $this->wait($state);
        }

        $media = MetaClient::post("{$ig}/media_publish", ['creation_id' => $state['container']], $token);
        $permalink = null;
        try {
            $permalink = MetaClient::get($media['id'], ['fields' => 'permalink'], $token)['permalink'] ?? null;
        } catch (SocialException $e) {
            // Ya está publicado: sin enlace no es un error.
        }

        return [
            'status'      => 'published',
            'external_id' => $media['id'] ?? null,
            'permalink'   => $permalink,
        ];
    }

    /** FINISHED = listo; IN_PROGRESS = esperar; ERROR/EXPIRED = falla. */
    protected function ready(string $containerId, string $token): bool
    {
        $res = MetaClient::get($containerId, ['fields' => 'status_code,status'], $token);
        $code = $res['status_code'] ?? 'IN_PROGRESS';

        if (in_array($code, ['ERROR', 'EXPIRED'], true)) {
            throw new SocialException('Instagram no pudo procesar el archivo (' . ($res['status'] ?? $code) . '). Revisa el formato: fotos JPG, video MP4 H.264 de 3 a 90 s.');
        }

        return in_array($code, ['FINISHED', 'PUBLISHED'], true);
    }

    protected function wait(array $state): array
    {
        return ['status' => 'processing', 'state' => $state, 'delay' => self::DELAY];
    }

    protected function caption(Post $post): string
    {
        return trim(trim((string) $post->content) . ($post->link ? "\n\n" . $post->link : ''));
    }
}
