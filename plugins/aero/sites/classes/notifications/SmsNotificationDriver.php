<?php namespace Aero\Sites\Classes\Notifications;

use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\NotificationChannel;
use Http;

class SmsNotificationDriver implements NotificationDriverInterface
{
    public function send(ContactSubmission $submission, NotificationChannel $channel): bool
    {
        $config = $channel->config;
        $accountSid = $config['account_sid'] ?? null;
        $authToken  = $config['auth_token'] ?? null;
        $from       = $config['from'] ?? null;
        $to         = $config['sms_to'] ?? null;

        if (!$accountSid || !$authToken || !$from || !$to) return false;

        $body = "Nuevo contacto de {$submission->name} ({$submission->email}): "
              . mb_substr($submission->message, 0, 100);

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $from,
                'To'   => $to,
                'Body' => $body,
            ]);

        return $response->successful();
    }
}
