<?php namespace Aero\Viral\Classes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use October\Rain\Exception\ApplicationException;

/**
 * Foto de perfil (solo quien ofrece su perfil a clientes). Subida común por el servidor, sin S3:
 * se valida como imagen real, se reduce a 800 px y se reescribe como JPEG (sin metadatos).
 * Queda en storage/app (privado); el panel la sirve con permiso.
 */
class PhotoService
{
    public const MAX_BYTES = 5242880;
    public const MAX_SIDE = 800;
    public const DIR = 'viral/photos';

    /** Guarda un borrador y devuelve su id (válido 3 h para esta sesión). */
    public static function stage(?UploadedFile $file, ?string $ip): string
    {
        if (!$file || !$file->isValid()) {
            throw new ApplicationException('No pudimos leer la foto. Prueba con otra.');
        }
        $key = 'viral-photo:' . $ip;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ApplicationException('Demasiados intentos. Prueba de nuevo en unos minutos.');
        }
        RateLimiter::hit($key, 600);

        if ($file->getSize() > self::MAX_BYTES) {
            throw new ApplicationException('La foto debe pesar menos de 5 MB.');
        }
        $info = @getimagesize($file->getRealPath());
        $src = null;
        if ($info) {
            $data = file_get_contents($file->getRealPath());
            $src = match ($info[2]) {
                IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP => @imagecreatefromstring($data),
                default => null,
            };
        }
        if (!$src) {
            throw new ApplicationException('Sube una foto JPG, PNG o WebP.');
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // PNG con transparencia → fondo blanco
        imagecopyresampled($out, $src, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        ob_start();
        imagejpeg($out, null, 85);
        $jpeg = (string) ob_get_clean();

        $id = Str::lower(Str::random(20));
        $path = self::DIR . '/drafts/' . UploadService::sessionToken() . '/' . $id . '.jpg';
        Storage::disk('local')->put($path, $jpeg);
        Cache::put('viral:photo:' . UploadService::sessionToken() . ':' . $id, $path, now()->addHours(3));

        return $id;
    }

    /** Ruta del borrador de esta sesión o null si no existe o venció. */
    public static function draftPath(?string $id): ?string
    {
        if (!$id || !preg_match('/^[a-z0-9]{20}$/', $id)) {
            return null;
        }
        $path = Cache::get('viral:photo:' . UploadService::sessionToken() . ':' . $id);

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }

    /** Mueve el borrador a su ruta definitiva y devuelve esa ruta (relativa al disco local). */
    public static function adopt(string $draftPath, string $applicationUuid): string
    {
        $dest = self::DIR . '/' . $applicationUuid . '.jpg';
        Storage::disk('local')->move($draftPath, $dest);

        return $dest;
    }
}
