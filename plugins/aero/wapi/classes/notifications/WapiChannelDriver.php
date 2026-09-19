<?php namespace Aero\Wapi\Classes\Notifications;

use Aero\Hello\Classes\Notifications\ChannelDriverInterface;
use Aero\Hello\Models\Account;
use Aero\Wapi\Classes\WapiClient;
use Aero\Wapi\Classes\WapiCredentials;

/**
 * Driver de WhatsApp Web (sesión propia vía QR, sin Business API de por
 * medio). A diferencia de ZernioChannelDriver, acá no hay ventana de 24h ni
 * plantillas: cualquier sesión conectada puede escribirle a cualquier número
 * en cualquier momento, así que `sendMessage` siempre es un envío directo.
 *
 * `$account->zernio_account_id` (columna genérica heredada de Hello, ver su
 * fields.yaml) guarda acá el `instanceId` de wapi, no un id de Zernio.
 */
class WapiChannelDriver implements ChannelDriverInterface
{
    public function __construct(protected ?WapiClient $client = null)
    {
    }

    /**
     * Un cliente inyectado en el constructor (tests) tiene prioridad; si no,
     * se resuelve por el perfil de la cuenta — mismo criterio que
     * ZernioChannelDriver::clientFor(), para que un perfil con
     * use_own_credentials y su propia wapi_api_key aísle sus envíos del
     * resto de la instalación.
     */
    protected function clientFor(Account $account): WapiClient
    {
        return $this->client ?? new WapiClient(WapiCredentials::apiKey($account->profile));
    }

    public function capabilities(): array
    {
        return [
            'text'       => true,
            'media'      => true,
            'templates'  => false,
            'window_24h' => false,
            'calls'      => false,
            'posts'      => false,
            'location'   => true,
            'contact'    => true,
            'poll'       => true,
        ];
    }

    public function sendMessage(Account $account, string $to, array $payload): string
    {
        $instanceId = $account->zernio_account_id;
        $to = ltrim($to, '+');

        // Ubicación, contacto y encuesta llegan estructurados en
        // provider_payload (message_type + campos propios de cada tipo).
        $structured = match ($payload['message_type'] ?? null) {
            'location' => [
                'type'         => 'location',
                'latitude'     => $payload['latitude'] ?? null,
                'longitude'    => $payload['longitude'] ?? null,
                'locationName' => $payload['location_name'] ?? null,
            ],
            'contact' => [
                'type'         => 'contact',
                'contactName'  => $payload['contact_name'] ?? null,
                'contactPhone' => $payload['contact_phone'] ?? null,
            ],
            'poll' => [
                'type'         => 'poll',
                'pollName'     => $payload['poll_name'] ?? null,
                'pollOptions'  => $payload['poll_options'] ?? null,
                'pollMultiple' => !empty($payload['poll_multiple']),
            ],
            default => null,
        };

        if ($structured) {
            $response = $this->clientFor($account)->post(
                "/instances/{$instanceId}/messages",
                array_filter(['to' => $to] + $structured, fn ($v) => $v !== null)
            );

            return $response['messageId'] ?? '';
        }

        $body = array_filter([
            'to'               => $to,
            'type'             => $this->resolveOutboundType($payload),
            'text'             => $payload['body'] ?? null,
            'caption'          => !empty($payload['media_url']) ? ($payload['body'] ?? null) : null,
            'mediaUrl'         => $payload['media_url'] ?? null,
            'replyToMessageId' => $payload['reply_to_message_id'] ?? null,
        ], fn ($value) => $value !== null);

        $response = $this->clientFor($account)->post("/instances/{$instanceId}/messages", $body);

        return $response['messageId'] ?? '';
    }

    /**
     * wapi distingue `text` de los tipos de adjunto (`image`, `video`,
     * `audio`, `document`); Zernio en cambio infiere el tipo del
     * Content-Type de la URL. `payload['media_type']` (seteado por
     * MessageComposer) ya trae el tipo lógico, así que se reutiliza tal cual.
     */
    protected function resolveOutboundType(array $payload): string
    {
        if (empty($payload['media_url'])) {
            return 'text';
        }

        return in_array($payload['media_type'] ?? null, ['image', 'video', 'audio', 'document'], true)
            ? $payload['media_type']
            : 'document';
    }

    /**
     * Payload recibido tal cual lo entrega wapi a su webhook:
     * {event, instanceId, timestamp, data: {id, body, type, from, to, author,
     * timestamp, fromMe, hasMedia, ack, isForwarded}} — ver
     * server/src/worker/WhatsAppInstance.js::_serializeMessage en el repo de
     * wapi. `from`/`to` son chatId con sufijo `@c.us` o `@g.us`, no el número
     * pelado que usa el resto de Hello (ContactIdentity.external_id), así que
     * se recorta acá.
     *
     * `hasMedia: true` no trae la URL — hay que pedirla aparte con
     * downloadMedia(), y solo mientras el mensaje siga en el store en memoria
     * de whatsapp-web.js (por eso se hace acá mismo, en el momento de
     * procesar el webhook, no perezoso).
     */
    public function parseWebhook(Account $account, array $payload): array
    {
        $data = $payload['data'] ?? [];
        $externalId = $data['id'] ?? null;

        return [
            'event'           => $this->canonicalEvent($payload['event'] ?? null),
            'external_id'     => $externalId,
            'conversation_id' => null,
            'from'            => $this->stripChatSuffix($data['from'] ?? ''),
            'type'            => $this->normalizeInboundType($data['type'] ?? null),
            'body'            => $data['body'] ?? null,
            'name'            => $data['pushName'] ?? null,
            'media_url'       => (!empty($data['hasMedia']) && $externalId)
                ? $this->downloadMedia($account, $externalId)
                : null,
            'timestamp'       => $data['timestamp'] ?? $payload['timestamp'] ?? null,
        ];
    }

    /**
     * GET /instances/:id/messages/:messageId/media (agregado a wapi el
     * 2026-09-18 junto con este driver — ver commit del repo de wapi). Sin
     * URL propia: descarga el base64 y lo materializa como System\Models\File
     * público (disco `uploads`), igual que cualquier adjunto ya persistido
     * en Hello. Devuelve null en cualquier falla (mensaje expirado del store
     * de whatsapp-web.js, instancia caída, etc.) — un adjunto no descargable
     * no debe tumbar el procesamiento del mensaje de texto que lo acompaña.
     */
    protected function downloadMedia(Account $account, string $externalId): ?string
    {
        try {
            $media = $this->clientFor($account)->get(
                "/instances/{$account->zernio_account_id}/messages/{$externalId}/media"
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($media['data'])) {
            return null;
        }

        $extension = self::extensionForMime($media['mimetype'] ?? null);
        $filename = ($media['filename'] ?: $externalId) . ($extension ? ".{$extension}" : '');

        $file = new \System\Models\File;
        $file->fromData(base64_decode($media['data']), $filename);
        $file->is_public = true;
        $file->save();

        return $file->getPath();
    }

    protected static function extensionForMime(?string $mimetype): ?string
    {
        return match ($mimetype) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg; codecs=opus', 'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            default => null,
        };
    }

    /**
     * Traduce el vocabulario de eventos de wapi al que ya entiende
     * ProcessWebhookEventJob::handle() (definido por Zernio): solo
     * `message.received` está soportado por ahora, el resto (message_ack,
     * qr, authenticated, disconnected, ...) no tiene handler en Hello y cae
     * en el log de "evento no manejado".
     */
    protected function canonicalEvent(?string $wapiEvent): string
    {
        return $wapiEvent === 'message' ? 'message.received' : ($wapiEvent ?? 'unknown');
    }

    protected function stripChatSuffix(string $chatId): string
    {
        return explode('@', $chatId)[0];
    }

    protected function normalizeInboundType(?string $wapiType): string
    {
        return match ($wapiType) {
            'chat' => 'text',
            'ptt' => 'audio',
            default => $wapiType ?? 'text',
        };
    }
}
