<?php namespace Aero\Livechat\Classes;

use Illuminate\Http\UploadedFile;
use Storage;
use Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adjuntos del widget/panel: se guardan FUERA de webroot (disco `local`,
 * `livechat_attachments/`) y se sirven por un controlador propio (no acceso
 * directo de archivos) — el nombre público es un token opaco de 40
 * caracteres, no el id del mensaje ni el nombre original.
 */
class AttachmentStorage
{
    public const MAX_BYTES = 8 * 1024 * 1024; // 8 MB

    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip',
    ];

    /** @return array{path:string,name:string,mime:string,size:int,token:string}|array{error:string} */
    public static function store(UploadedFile $file): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            return ['error' => 'El archivo supera los 8 MB.'];
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['error' => 'Tipo de archivo no permitido.'];
        }

        $token = Str::random(40);
        $path = "livechat_attachments/{$token}.{$ext}";

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return [
            'path'  => $path,
            'name'  => $file->getClientOriginalName(),
            'mime'  => $file->getClientMimeType() ?: 'application/octet-stream',
            'size'  => $file->getSize(),
            'token' => $token,
        ];
    }

    public static function stream(string $path, string $mime, string $downloadName): StreamedResponse
    {
        $disposition = str_starts_with($mime, 'image/') || $mime === 'application/pdf' ? 'inline' : 'attachment';

        return Storage::disk('local')->response($path, $downloadName, [
            'Content-Type' => $mime,
        ], $disposition);
    }
}
