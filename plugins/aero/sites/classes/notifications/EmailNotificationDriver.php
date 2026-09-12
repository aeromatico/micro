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

        $mailer = $this->applySmtpConfig($config);

        $fromName = $config['from_name'] ?? ($submission->tenant->name ?? 'Notificación');
        $template = $config['template'] ?? 'aero.sites::mail.contact';

        Mail::mailer($mailer)->send($template, [
            'submission' => $submission,
            'tenant'     => $submission->tenant,
        ], function ($message) use ($to, $fromName, $submission) {
            $message->to($to)
                ->from($to, $fromName)
                ->subject("Nuevo mensaje de contacto — {$submission->name}");
        });

        return true;
    }

    protected function applySmtpConfig(array $config): string
    {
        if (empty($config['smtp_host'])) {
            return config('mail.default');
        }

        $encryption = $config['smtp_encryption'] ?: 'tls';

        config([
            'mail.mailers.smtp.host'       => $config['smtp_host'],
            'mail.mailers.smtp.port'       => (int) ($config['smtp_port'] ?? 587),
            'mail.mailers.smtp.scheme'     => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username'   => $config['smtp_username'] ?? null,
            'mail.mailers.smtp.password'   => $config['smtp_password'] ?? null,
        ]);

        Mail::purge('smtp');

        return 'smtp';
    }
}
