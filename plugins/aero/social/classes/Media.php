<?php namespace Aero\Social\Classes;

use Aero\Social\Models\Post;

/** Archivos de un post, normalizados para los drivers (URL pública + tipo). */
class Media
{
    public const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif'];
    public const VIDEO_EXT = ['mp4', 'mov'];

    /** @return array<int, array{url:string, ext:string, video:bool, name:string, size:int, mime:?string, file:\System\Models\File}> */
    public static function of(Post $post): array
    {
        return $post->media->map(function ($file) {
            $ext = strtolower($file->getExtension());

            return [
                'url'   => $file->getPath(),
                'ext'   => $ext,
                'video' => in_array($ext, self::VIDEO_EXT, true),
                'name'  => $file->file_name,
                'size'  => (int) $file->file_size,
                'mime'  => $file->content_type,
                'file'  => $file,
            ];
        })->values()->all();
    }

    /**
     * Abre el archivo para leerlo por trozos (sirve con disco local o remoto).
     *
     * @return resource
     */
    public static function stream(array $m)
    {
        $stream = $m['file']->getDisk()->readStream($m['file']->getDiskPath());
        if (!$stream) {
            throw new SocialException("No se pudo leer el archivo «{$m['name']}».");
        }

        return $stream;
    }

    /** Lee exactamente $length bytes (o hasta el final). */
    public static function read($stream, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length && !feof($stream)) {
            $buffer .= fread($stream, $length - strlen($buffer));
        }

        return $buffer;
    }
}

