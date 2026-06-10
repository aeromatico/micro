<?php namespace Aero\Sites\Classes\Notifications;

use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\NotificationChannel;
use Http;

class TelegramNotificationDriver implements NotificationDriverInterface
{
    public function send(ContactSubmission $submission, NotificationChannel $channel): bool
    {
        $config = $channel->config;
        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (!$botToken || !$chatId) return false;

        $tenantName = $submission->tenant->name ?? '';
        $text = "📩 *Nuevo contacto — {$tenantName}*\n\n"
              . "*Nombre:* {$submission->name}\n"
              . "*Email:* {$submission->email}\n"
              . ($submission->phone ? "*Teléfono:* {$submission->phone}\n" : '')
              . "\n*Mensaje:*\n{$submission->message}";

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ]);

        return $response->successful() && ($response->json('ok') === true);
    }
}
