<?php namespace Aero\Livechat\Classes;

use Http;
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
        // audio/video: notas de voz y clips enviados por el agente desde el PWA de aero/chat (ver HelloBridge/LivechatChannelDriver).
        'ogg', 'mp3', 'm4a', 'webm', 'mp4',
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

    /**
     * Materializa un adjunto que el agente ya envió por otro canal (System\Models\File
     * público, del PWA de aero/chat) como un adjunto propio de livechat — mismo disco
     * y esquema de token que uno subido directo por el widget/panel. Usado por
     * LivechatChannelDriver::sendMessage para que el visitante lo vea igual que
     * cualquier otro adjunto (con su URL vía attachments/{token}).
     *
     * @return array{path:string,name:string,mime:string,size:int,token:string}|array{error:string}
     */
    public static function storeFromRemote(string $url, ?string $mime = null, ?string $name = null): array
    {
        $response = Http::timeout(20)->get($url);
        if (!$response->successful()) {
            return ['error' => 'No se pudo descargar el archivo enviado.'];
        }

        $mime = $mime ?: strtok((string) $response->header('Content-Type'), ';') ?: null;
        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            return ['error' => 'El archivo supera los 8 MB.'];
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['error' => 'Tipo de archivo no permitido.'];
        }

        $token = Str::random(40);
        $path = "livechat_attachments/{$token}.{$ext}";

        Storage::disk('local')->put($path, $body);

        return [
            'path'  => $path,
            'name'  => $name ?: basename(parse_url($url, PHP_URL_PATH) ?: "archivo.{$ext}"),
            'mime'  => $mime ?: 'application/octet-stream',
            'size'  => strlen($body),
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
