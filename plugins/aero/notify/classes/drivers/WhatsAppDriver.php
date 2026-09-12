<?php namespace Aero\Notify\Classes\Drivers;

/**
 * Envía por WhatsApp vía Aero.Hello (Zernio). Hello es opcional: un consumidor
 * de Notify puede correr sin él instalado, así que se comprueba class_exists()
 * antes de tocarlo.
 *
 * Un tenant no siempre tiene su propia cuenta/API key de Zernio (eso es
 * opcional en Aero.Hello, ver Profile.use_own_credentials). Primero se intenta
 * la cuenta del tenant; si no tiene ninguna habilitada, se cae a la primera
 * cuenta de WhatsApp habilitada en la plataforma (el número/agente general).
 */
class WhatsAppDriver implements ChannelDriverInterface
{
    /**
     * $context puede traer `media_url` (+ `media_type`: image|video|audio|file,
     * default 'image') para adjuntar un archivo — ver
     * Aero\Hello\Classes\Notifications\ZernioChannelDriver, que necesita el
     * tipo explícito o Zernio lo manda como "file" genérico.
     */
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            throw new \RuntimeException('Aero.Hello no está instalado: no se puede enviar por WhatsApp.');
        }

        $tenantId = $context['tenant_id'] ?? null;
        $media = array_filter([
            'media_url'  => $context['media_url'] ?? null,
            'media_type' => $context['media_type'] ?? null,
        ], fn ($v) => $v !== null);

        try {
            if ($tenantId) {
                $message = \Aero\Hello\Classes\Hello::send($address, $body, [
                    'platform'  => 'whatsapp',
                    'tenant_id' => $tenantId,
                ] + $media);

                return (string) $message->zernio_message_id;
            }
        } catch (\RuntimeException $e) {
            // Sin cuenta propia para este tenant: se intenta la cuenta
            // general más abajo en vez de fallar la entrega.
        }

        $message = \Aero\Hello\Classes\Hello::send($address, $body, ['platform' => 'whatsapp'] + $media);

        return (string) $message->zernio_message_id;
    }
}
