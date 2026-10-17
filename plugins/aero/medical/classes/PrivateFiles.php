<?php namespace Aero\Medical\Classes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;

/**
 * Archivos clínicos: CIFRADOS en disco con la clave de la aplicación y con nombre aleatorio.
 * Aunque el servidor web publicara la carpeta, lo que hay es texto cifrado. Solo se leen por
 * Controllers\Files (permiso clínico + tenant + bitácora). El tipo se decide por el CONTENIDO
 * real, no por la extensión ni por lo que declare el navegador.
 */
class PrivateFiles
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** mime real => extensión aceptada(s) */
    public const ALLOWED = [
        'application/pdf'  => ['pdf'],
        'image/png'        => ['png'],
        'image/jpeg'       => ['jpg', 'jpeg'],
        'image/webp'       => ['webp'],
        'text/plain'       => ['txt'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    /** Se muestran en el navegador; el resto se descarga. */
    public const INLINE = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];

    /** Solo para pruebas: carpeta alternativa para no tocar archivos reales. */
    public static ?string $dirOverride = null;

    public static function dir(): string
    {
        return static::$dirOverride ?? storage_path('app/medical-private');
    }

    /**
     * Valida y guarda un archivo subido.
     * @return array{storage_name:string,mime:string,size:int,sha256:string,original_name:string}
     */
    public static function put(UploadedFile $file, int $tenantId): array
    {
        if (!$file->isValid()) {
            throw new MedicalException('El archivo no se subió bien. Intente de nuevo.');
        }
        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new MedicalException('El archivo está vacío.');
        }
        if ($size > self::MAX_BYTES) {
            throw new MedicalException('El archivo pesa más de 20 MB.');
        }

        $path = $file->getRealPath();
        $mime = static::detectMime($path, (string) $file->getClientOriginalExtension());
        if (!$mime) {
            throw new MedicalException('Tipo de archivo no permitido. Se aceptan PDF, imágenes (PNG, JPG, WebP), Word y texto.');
        }

        $data = (string) file_get_contents($path);
        $name = bin2hex(random_bytes(24));
        $target = static::path($tenantId, $name);

        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
            throw new MedicalException('No se pudo preparar el almacenamiento de archivos.');
        }
        if (file_put_contents($target, Crypt::encryptString($data), LOCK_EX) === false) {
            throw new MedicalException('No se pudo guardar el archivo.');
        }
        @chmod($target, 0640);

        return [
            'storage_name'  => $name,
            'mime'          => $mime,
            'size'          => $size,
            'sha256'        => hash('sha256', $data),
            'original_name' => mb_substr(static::cleanName((string) $file->getClientOriginalName()), 0, 200),
        ];
    }

    public static function read(int $tenantId, string $storageName): string
    {
        $path = static::path($tenantId, $storageName);
        if (!is_file($path)) {
            throw new MedicalException('El archivo ya no está en el almacenamiento.');
        }

        return Crypt::decryptString((string) file_get_contents($path));
    }

    public static function forget(int $tenantId, string $storageName): void
    {
        @unlink(static::path($tenantId, $storageName));
    }

    protected static function path(int $tenantId, string $name): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $name)) {
            throw new MedicalException('Nombre de archivo no válido.');
        }

        return static::dir() . '/' . $tenantId . '/' . substr($name, 0, 2) . '/' . $name;
    }

    /** Mime real validado contra la extensión; null si no se acepta. */
    public static function detectMime(string $path, string $extension): ?string
    {
        $ext = strtolower($extension);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);

        // docx es un zip: debe traer word/document.xml.
        if (in_array($mime, ['application/zip', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true) && $ext === 'docx') {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $ok = $zip->locateName('word/document.xml') !== false;
                $zip->close();

                return $ok ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : null;
            }

            return null;
        }
        // doc es un contenedor OLE.
        if ($ext === 'doc' && in_array($mime, ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'], true)) {
            return 'application/msword';
        }
        if ($ext === 'doc' && str_starts_with((string) file_get_contents($path, false, null, 0, 8), "\xD0\xCF\x11\xE0")) {
            return 'application/msword';
        }
        if ($mime === 'text/plain' && $ext === 'txt') {
            return 'text/plain';
        }
        if (isset(self::ALLOWED[$mime]) && in_array($ext, self::ALLOWED[$mime], true) && !in_array($mime, ['application/msword', 'text/plain'], true)) {
            return $mime;
        }

        return null;
    }

    public static function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name);

        return trim((string) $name) !== '' ? trim((string) $name) : 'archivo';
    }
}
