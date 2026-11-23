<?php namespace Aero\Social\Classes\Drivers;

use Aero\Social\Models\Account;
use Aero\Social\Models\Post;
use Aero\Social\Models\PostTarget;

interface DriverInterface
{
    /** Errores legibles si el post no se puede publicar en esta red (vacío = ok). */
    public function validate(Post $post, array $media): array;

    /**
     * Publica (o empieza a publicar). Devuelve:
     *   ['status' => 'published', 'external_id' => …, 'permalink' => …]
     *   ['status' => 'processing', 'state' => [...], 'delay' => segundos]
     *
     * @throws \Aero\Social\Classes\SocialException
     */
    public function publish(PostTarget $target, Post $post, Account $account, array $media): array;

    /** Continúa una publicación en proceso (mismo formato de retorno). */
    public function check(PostTarget $target, Post $post, Account $account): array;
}
