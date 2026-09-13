<?php namespace Aero\Notify\Classes\Drivers;

use Http;

/**
 * Envía por el bot de Telegram propio del tenant. No hay bot de plataforma:
 * sin channel_config (ver Notify::deliverOne / Models\Channel) esta entrega
 * siempre se salta como no_address, porque $address (chat_id) nunca se
 * resuelve desde AudienceResolver.
 */
class TelegramDriver implements ChannelDriverInterface
{
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        $botToken = $context['channel_config']['bot_token'] ?? null;

        if (!$botToken) {
            throw new \RuntimeException('Canal Telegram sin bot_token configurado.');
        }

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $address,
            'text'    => strip_tags($body),
        ]);

        if (!$response->successful() || $response->json('ok') !== true) {
            throw new \RuntimeException('Telegram rechazó el envío: ' . $response->body());
        }

        return (string) $response->json('result.message_id');
    }
}
