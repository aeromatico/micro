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
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            throw new \RuntimeException('Aero.Hello no está instalado: no se puede enviar por WhatsApp.');
        }

        $tenantId = $context['tenant_id'] ?? null;

        try {
            if ($tenantId) {
                $message = \Aero\Hello\Classes\Hello::send($address, $body, [
                    'platform'  => 'whatsapp',
                    'tenant_id' => $tenantId,
                ]);

                return (string) $message->zernio_message_id;
            }
        } catch (\RuntimeException $e) {
            // Sin cuenta propia para este tenant: se intenta la cuenta
            // general más abajo en vez de fallar la entrega.
        }

        $message = \Aero\Hello\Classes\Hello::send($address, $body, ['platform' => 'whatsapp']);

        return (string) $message->zernio_message_id;
    }
}
