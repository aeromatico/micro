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
