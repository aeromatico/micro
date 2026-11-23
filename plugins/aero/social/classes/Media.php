<?php namespace Aero\Social\Classes;

use Aero\Social\Models\Post;

/** Archivos de un post, normalizados para los drivers (URL pública + tipo). */
class Media
{
    public const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif'];
    public const VIDEO_EXT = ['mp4', 'mov'];

    /** @return array<int, array{url:string, ext:string, video:bool, name:string}> */
    public static function of(Post $post): array
    {
        return $post->media->map(function ($file) {
            $ext = strtolower($file->getExtension());

            return [
                'url'   => $file->getPath(),
                'ext'   => $ext,
                'video' => in_array($ext, self::VIDEO_EXT, true),
                'name'  => $file->file_name,
            ];
        })->values()->all();
    }
}
