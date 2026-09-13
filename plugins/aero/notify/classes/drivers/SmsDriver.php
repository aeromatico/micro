<?php namespace Aero\Notify\Classes\Drivers;

use Http;

/**
 * Envía por SMS vía Twilio, con la cuenta propia del tenant. No hay cuenta de
 * plataforma: sin channel_config (ver Notify::deliverOne / Models\Channel)
 * esta entrega siempre se salta como no_address.
 */
class SmsDriver implements ChannelDriverInterface
{
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        $config = $context['channel_config'] ?? [];
        $accountSid = $config['account_sid'] ?? null;
        $authToken  = $config['auth_token'] ?? null;
        $from       = $config['from'] ?? null;

        if (!$accountSid || !$authToken || !$from) {
            throw new \RuntimeException('Canal SMS sin credenciales de Twilio configuradas.');
        }

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $from,
                'To'   => $address,
                'Body' => strip_tags($body),
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Twilio rechazó el envío: ' . $response->body());
        }

        return (string) $response->json('sid');
    }
}
