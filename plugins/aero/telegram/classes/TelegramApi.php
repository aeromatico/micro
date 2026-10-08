<?php namespace Aero\Telegram\Classes;

use Http;
use RuntimeException;

/**
 * Cliente mínimo de la Bot API de Telegram. El token va en la ruta, como exige Telegram.
 */
class TelegramApi
{
    public function __construct(protected string $token)
    {
    }

    public function call(string $method, array $params = [], string $verb = 'POST'): array
    {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;

        $request = Http::timeout(15);
        $response = $verb === 'GET' ? $request->get($url, $params) : $request->asJson()->post($url, $params);

        $body = $response->json() ?? [];
        if (!$response->successful() || empty($body['ok'])) {
            throw new RuntimeException($body['description'] ?? 'Telegram respondió con error (HTTP ' . $response->status() . ').');
        }

        return $body['result'] ?? [];
    }

    public function getMe(): array
    {
        return $this->call('getMe', [], 'GET');
    }

    public function setWebhook(string $url, string $secret): array
    {
        return $this->call('setWebhook', [
            'url'             => $url,
            'secret_token'    => $secret,
            'allowed_updates' => ['message'],
        ]);
    }
}
