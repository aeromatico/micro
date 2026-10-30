<?php namespace Aero\Viral\Classes;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Almacenamiento privado de Viral (muestras de video y documentos KYC).
 * Todo va al disco `viral` (config/filesystems.php, variables VIRAL_S3_*). Nada es público:
 * se lee y se sube solo con URLs firmadas de corta vida.
 */
class ViralStorage
{
    public const DISK = 'viral';
    /** Vida de las URLs firmadas, en minutos. */
    public const UPLOAD_TTL = 120;   // subir 100 MB desde un celular lento
    public const READ_TTL = 10;

    public static function isConfigured(): bool
    {
        $c = config('filesystems.disks.' . self::DISK, []);

        return !empty($c['key']) && !empty($c['secret']) && !empty($c['bucket']) && class_exists(\Aws\S3\S3Client::class);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /** Cliente S3 del SDK (multipart y URLs firmadas). */
    public static function client(): \Aws\S3\S3Client
    {
        return self::disk()->getClient();
    }

    public static function bucket(): string
    {
        return (string) config('filesystems.disks.' . self::DISK . '.bucket');
    }

    /** Prefijo configurado (VIRAL_S3_PREFIX): el disco ya lo antepone a las rutas relativas. */
    public static function prefix(): string
    {
        return trim((string) config('filesystems.disks.' . self::DISK . '.root'), '/');
    }

    /** Clave completa en el bucket para una ruta relativa del disco. */
    public static function fullKey(string $path): string
    {
        $prefix = self::prefix();

        return ($prefix !== '' ? $prefix . '/' : '') . ltrim($path, '/');
    }

    // --- Rutas relativas (nunca derivadas de nombres de archivo del usuario) ---

    public static function samplePath(string $applicationUuid, string $ext = 'mp4'): string
    {
        return 'samples/' . $applicationUuid . '/' . Str::lower(Str::random(24)) . '.' . self::safeExt($ext);
    }

    /** Subidas aún sin postulación: viven bajo un token de sesión y se adoptan al enviar el formulario. */
    public static function draftPath(string $token, string $ext): string
    {
        return 'drafts/' . $token . '/' . Str::lower(Str::random(24)) . '.' . self::safeExt($ext);
    }

    /**
     * POST prefirmado directo del navegador al bucket. La política exige la clave exacta, el tipo
     * declarado y de 1 byte a $maxBytes, así que S3 rechaza lo que no cumpla aunque el cliente mienta.
     *
     * @return array{url:string,fields:array<string,string>}
     */
    public static function presignedPost(string $path, string $mime, int $maxBytes): array
    {
        $key = self::fullKey($path);
        $post = new \Aws\S3\PostObjectV4(
            self::client(),
            self::bucket(),
            ['key' => $key, 'Content-Type' => $mime],
            [['bucket' => self::bucket()], ['eq', '$key', $key], ['eq', '$Content-Type', $mime], ['content-length-range', 1, $maxBytes]],
            '+' . self::UPLOAD_TTL . ' minutes'
        );

        return ['url' => $post->getFormAttributes()['action'], 'fields' => $post->getFormInputs()];
    }

    /** Metadatos del objeto (ContentLength, ContentType) o null si no existe. */
    public static function head(string $path): ?array
    {
        try {
            $r = self::client()->headObject(['Bucket' => self::bucket(), 'Key' => self::fullKey($path)]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return null;
        }

        return ['size' => (int) $r['ContentLength'], 'mime' => (string) ($r['ContentType'] ?? '')];
    }

    /** Primeros bytes del objeto, para comprobar que el contenido es lo que dice el tipo. */
    public static function firstBytes(string $path, int $n = 32): string
    {
        $r = self::client()->getObject(['Bucket' => self::bucket(), 'Key' => self::fullKey($path), 'Range' => 'bytes=0-' . ($n - 1)]);

        return (string) $r['Body'];
    }

    /** URL de lectura firmada y de corta vida. */
    public static function readUrl(string $path, int $minutes = self::READ_TTL): string
    {
        $cmd = self::client()->getCommand('GetObject', ['Bucket' => self::bucket(), 'Key' => self::fullKey($path)]);

        return (string) self::client()->createPresignedRequest($cmd, '+' . $minutes . ' minutes')->getUri();
    }

    /** @param string $part front|back|selfie */
    public static function kycPath(string $applicationUuid, string $part, string $ext = 'jpg'): string
    {
        abort_unless(in_array($part, ['front', 'back', 'selfie'], true), 500);

        return 'kyc/' . $applicationUuid . '/' . $part . '-' . Str::lower(Str::random(12)) . '.' . self::safeExt($ext);
    }

    protected static function safeExt(string $ext): string
    {
        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));

        return in_array($ext, ['mp4', 'mov', 'webm', 'm4v', 'jpg', 'jpeg', 'png', 'webp', 'heic'], true) ? $ext : 'bin';
    }
}
