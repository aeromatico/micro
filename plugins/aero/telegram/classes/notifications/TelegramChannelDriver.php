<?php namespace Aero\Telegram\Classes\Notifications;

use Aero\Hello\Classes\Notifications\ChannelDriverInterface;
use Aero\Hello\Models\Account;
use Aero\Telegram\Classes\TelegramApi;
use Aero\Telegram\Models\TelegramBot;
use RuntimeException;

/**
 * Chats privados con un bot de Telegram. Telegram solo permite escribirle a
 * quien ya abrió el chat con el bot, así que no hay envíos en frío ni
 * plantillas: cada envío es una respuesta dentro de un chat existente.
 *
 * `zernio_account_id` guarda acá 'telegram:<bot_id>' (columna genérica de Hello).
 */
class TelegramChannelDriver implements ChannelDriverInterface
{
    public function sendMessage(Account $account, string $to, array $payload): string
    {
        if (!empty($payload['message_type'])) {
            throw new RuntimeException("Telegram no soporta mensajes de tipo '{$payload['message_type']}' desde el omnichat.");
        }

        $api = $this->apiFor($account);
        $body = trim((string) ($payload['body'] ?? ''));
        $media = $payload['media_url'] ?? null;

        if ($media) {
            $isPhoto = (bool) preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $media);
            $result = $api->call($isPhoto ? 'sendPhoto' : 'sendDocument', array_filter([
                'chat_id'          => $to,
                $isPhoto ? 'photo' : 'document' => $media,
                'caption'          => $body !== '' ? $body : null,
            ], fn ($v) => $v !== null));
        } else {
            if ($body === '') {
                throw new RuntimeException('El mensaje de Telegram no puede ir vacío.');
            }
            $result = $api->call('sendMessage', ['chat_id' => $to, 'text' => $body]);
        }

        return (string) ($result['message_id'] ?? '');
    }

    public function parseWebhook(Account $account, array $payload): array
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? [];

        if (empty($message)) {
            return ['from' => '', 'event' => 'message.received'];
        }

        $from = $message['chat']['id'] ?? $message['from']['id'] ?? null;
        $sender = $message['from'] ?? [];
        $name = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));

        return [
            'event'           => 'message.received',
            'external_id'     => isset($message['message_id']) ? (string) $message['message_id'] : null,
            'conversation_id' => null,
            'from'            => $from !== null ? (string) $from : '',
            'type'            => $this->inboundType($message),
            'body'            => $message['text'] ?? $message['caption'] ?? null,
            'media_url'       => null,
            'name'            => $name !== '' ? $name : ($sender['username'] ?? null),
            'timestamp'       => $message['date'] ?? null,
        ];
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
            'location'   => false,
            'contact'    => false,
            'poll'       => false,
        ];
    }

    protected function apiFor(Account $account): TelegramApi
    {
        $bot = TelegramBot::where('account_id', $account->id)->first();
        if (!$bot || !$bot->token) {
            throw new RuntimeException('La cuenta de Telegram no tiene token de bot configurado.');
        }

        return new TelegramApi($bot->token);
    }

    protected function inboundType(array $message): string
    {
        foreach (['photo' => 'image', 'document' => 'document', 'audio' => 'audio', 'voice' => 'audio', 'video' => 'video', 'sticker' => 'sticker', 'location' => 'location'] as $field => $type) {
            if (isset($message[$field])) {
                return $type;
            }
        }

        return 'text';
    }
}
