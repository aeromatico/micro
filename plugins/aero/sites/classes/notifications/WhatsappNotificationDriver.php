<?php namespace Aero\Sites\Classes\Notifications;

use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\NotificationChannel;
use Http;

class WhatsappNotificationDriver implements NotificationDriverInterface
{
    public function send(ContactSubmission $submission, NotificationChannel $channel): bool
    {
        $config = $channel->config;
        $phoneNumberId = $config['phone_number_id'] ?? null;
        $to = $config['whatsapp_to'] ?? null;
        $accessToken = $config['access_token'] ?? null;
        $templateName = $config['template_name'] ?? 'contact_notification';

        if (!$phoneNumberId || !$to || !$accessToken) return false;

        $to = preg_replace('/[^0-9]/', '', $to);

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => 'es'],
                    'components' => [
                        [
                            'type'       => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $submission->name],
                                ['type' => 'text', 'text' => $submission->email],
                                ['type' => 'text', 'text' => $submission->phone ?? 'N/A'],
                                ['type' => 'text', 'text' => mb_substr($submission->message, 0, 500)],
                            ],
                        ],
                    ],
                ],
            ]);

        return $response->successful();
    }
}
