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

    /** Para adjuntos remotos sin extensión en la URL (ej. el QR de Aero.Pay: /api/v1/pay/public/qr/{ref}/image) — ver storeFromRemote(). */
    protected const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'audio/ogg' => 'ogg', 'audio/webm' => 'webm', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a',
        'video/mp4' => 'mp4', 'video/webm' => 'webm',
        'application/pdf' => 'pdf',
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
    /**
     * $kind ('audio'|'video'|'image'|'document', ver InboxController::attachment de
     * aero/chat) manda sobre el Content-Type que devuelva el servidor de origen: el
     * disco de archivos públicos de October no siempre lo reporta bien (un .webm sin
     * mapear cae en application/octet-stream ahí) y eso hacía que el adjunto llegara
     * al widget como "📎 archivo" en vez de reproductor de audio/video.
     *
     * No toda URL de origen trae extensión en la ruta (ej. el QR de Aero.Pay:
     * /api/v1/pay/public/qr/{ref}/image) — en ese caso se deriva del mime ya
     * resuelto (kind, o si no el Content-Type real), vía MIME_EXTENSIONS.
     */
    public static function storeFromRemote(string $url, ?string $mime = null, ?string $name = null, ?string $kind = null): array
    {
        $response = Http::timeout(20)->get($url);
        if (!$response->successful()) {
            return ['error' => 'No se pudo descargar el archivo enviado.'];
        }

        $urlExt = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if (!$mime && $kind && $urlExt && in_array($kind, ['audio', 'video', 'image'], true)) {
            $mime = "{$kind}/" . ($urlExt === 'jpg' ? 'jpeg' : $urlExt);
        }
        $mime = $mime ?: strtok((string) $response->header('Content-Type'), ';') ?: null;
        $ext = $urlExt ?: (self::MIME_EXTENSIONS[$mime] ?? null);

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            return ['error' => 'El archivo supera los 8 MB.'];
        }

        if (!$ext || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['error' => 'Tipo de archivo no permitido.'];
        }

        $token = Str::random(40);
        $path = "livechat_attachments/{$token}.{$ext}";

        Storage::disk('local')->put($path, $body);

        // basename() de una URL sin extensión (el QR de Aero.Pay) da un nombre sin
        // punto (ej. "image") — se le pega la extensión ya resuelta en ese caso.
        $base = basename(parse_url($url, PHP_URL_PATH) ?: '');

        return [
            'path'  => $path,
            'name'  => $name ?: (str_contains($base, '.') ? $base : "archivo.{$ext}"),
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
