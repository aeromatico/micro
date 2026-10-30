<?php namespace Aero\Viral\Classes;

use Aero\Viral\Models\Application;
use Aero\Viral\Models\Sample;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use October\Rain\Exception\ApplicationException;

/**
 * Subida y grabación de muestras: del navegador directo a S3 con POST prefirmado.
 * Antes de enviar la postulación los archivos viven en drafts/{token} (token de sesión);
 * al enviar se comprueban en S3 (existe, tamaño, tipo, firma del contenido) y se pasan a samples/{uuid}.
 */
class UploadService
{
    public const MAX_FILES = 3;
    public const MAX_SECONDS = 600;
    public const MIMES = [
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm', 'video/x-m4v' => 'm4v',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    ];

    /** Normaliza «video/webm;codecs=vp9» a «video/webm». */
    public static function baseMime(?string $mime): string
    {
        return strtolower(trim(explode(';', (string) $mime)[0]));
    }

    public static function sessionToken(): string
    {
        $t = Session::get('viral_draft');
        if (!$t) {
            Session::put('viral_draft', $t = (string) Str::uuid());
        }

        return $t;
    }

    protected static function cacheKey(string $id): string
    {
        return 'viral:upload:' . self::sessionToken() . ':' . $id;
    }

    /** Autoriza una subida y devuelve el POST prefirmado. */
    public static function init(string $mime, int $size, string $kind, ?string $ip): array
    {
        if (!ViralStorage::isConfigured()) {
            throw new ApplicationException('La subida de archivos se activará muy pronto.');
        }
        $mime = self::baseMime($mime);
        if (!isset(self::MIMES[$mime])) {
            throw new ApplicationException('Formato no admitido. Usa MP4, MOV o WebM (o una imagen JPG, PNG o WebP).');
        }
        if ($size < 1 || $size > Sample::MAX_BYTES) {
            throw new ApplicationException('El archivo debe pesar menos de 100 MB.');
        }
        $key = 'viral-upload:' . $ip;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw new ApplicationException('Demasiados intentos. Prueba de nuevo en unos minutos.');
        }
        RateLimiter::hit($key, 600);

        $list = Session::get('viral_draft_ids', []);
        if (count($list) >= self::MAX_FILES * 3) { // reintentos incluidos
            throw new ApplicationException('Demasiados archivos. Recarga la página para empezar de nuevo.');
        }
        $id = Str::lower(Str::random(20));
        $path = ViralStorage::draftPath(self::sessionToken(), self::MIMES[$mime]);
        Cache::put(self::cacheKey($id), ['path' => $path, 'mime' => $mime, 'kind' => $kind === 'recording' ? 'recording' : 'file'], now()->addHours(3));
        Session::put('viral_draft_ids', array_merge($list, [$id]));

        return ['id' => $id] + ViralStorage::presignedPost($path, $mime, Sample::MAX_BYTES);
    }

    /**
     * Comprueba en S3 las subidas que el formulario dice haber hecho.
     *
     * @param  array<int,array{id:string,duration?:int}> $items
     * @return array<int,array>  [path, mime, size, kind, duration]
     */
    public static function verify(array $items): array
    {
        if (!$items) {
            return [];
        }
        if (count($items) > self::MAX_FILES) {
            throw ValidationException::withMessages(['links' => 'Máximo ' . self::MAX_FILES . ' archivos.']);
        }
        $out = [];
        foreach ($items as $it) {
            $id = is_array($it) ? (string) ($it['id'] ?? '') : '';
            $meta = $id !== '' ? Cache::get(self::cacheKey($id)) : null;
            $bad = fn ($m) => ValidationException::withMessages(['links' => $m]);
            if (!$meta) {
                throw $bad('Una subida venció. Vuelve a subir el archivo.');
            }
            $head = ViralStorage::head($meta['path']);
            if (!$head || $head['size'] < 1) {
                throw $bad('No encontramos un archivo que subiste. Vuelve a subirlo.');
            }
            if ($head['size'] > Sample::MAX_BYTES) {
                ViralStorage::disk()->delete($meta['path']);
                throw $bad('El archivo debe pesar menos de 100 MB.');
            }
            if (self::baseMime($head['mime']) !== $meta['mime'] || !self::signatureMatches($meta['mime'], ViralStorage::firstBytes($meta['path']))) {
                ViralStorage::disk()->delete($meta['path']);
                throw $bad('Un archivo no es del formato que dice ser. Sube un video o imagen válidos.');
            }
            $out[] = $meta + ['id' => $id, 'size' => $head['size'], 'duration' => min(self::MAX_SECONDS, max(0, (int) ($it['duration'] ?? 0))) ?: null];
        }

        return $out;
    }

    /** Pasa los archivos verificados a la carpeta definitiva de la postulación y crea las muestras. */
    public static function adopt(Application $app, array $verified): void
    {
        foreach ($verified as $v) {
            $ext = self::MIMES[$v['mime']];
            $dest = ViralStorage::samplePath($app->uuid, $ext);
            ViralStorage::disk()->copy($v['path'], $dest);
            ViralStorage::disk()->delete($v['path']);
            Cache::forget(self::cacheKey($v['id']));
            Sample::create([
                'application_id' => $app->id, 'kind' => $v['kind'], 'path' => $dest, 'mime' => $v['mime'],
                'size_bytes' => $v['size'], 'duration_seconds' => $v['duration'], 'status' => 'ready',
            ]);
        }
    }

    public static function signatureMatches(string $mime, string $b): bool
    {
        return match ($mime) {
            'video/mp4', 'video/quicktime', 'video/x-m4v' => substr($b, 4, 4) === 'ftyp',
            'video/webm' => str_starts_with($b, "\x1A\x45\xDF\xA3"),
            'image/jpeg' => str_starts_with($b, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($b, "\x89PNG"),
            'image/webp' => str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WEBP',
            default => false,
        };
    }
}
