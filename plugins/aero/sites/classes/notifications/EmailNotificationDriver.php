<?php namespace Aero\Sites\Classes\Notifications;

use Aero\Sites\Models\ContactSubmission;
use Aero\Sites\Models\NotificationChannel;
use Mail;

class EmailNotificationDriver implements NotificationDriverInterface
{
    public function send(ContactSubmission $submission, NotificationChannel $channel): bool
    {
        $config = $channel->config;
        $to = $config['to'] ?? null;

        if (!$to) return false;

        $template = $config['template'] ?? 'aero.sites::mail.contact';

        Mail::send($template, [
            'submission' => $submission,
            'tenant'     => $submission->tenant,
        ], function ($message) use ($to, $submission) {
            $message->to($to)
                ->subject("Nuevo mensaje de contacto — {$submission->name}");
        });

        return true;
    }
}
