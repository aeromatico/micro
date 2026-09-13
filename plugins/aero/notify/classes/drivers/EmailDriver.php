<?php namespace Aero\Notify\Classes\Drivers;

use Mail;

class EmailDriver implements ChannelDriverInterface
{
    /**
     * $context puede traer un adjunto: `attachment_binary` (+ opcional
     * `attachment_filename`/`attachment_mime`) para datos en memoria, o
     * `attachment_url` para un archivo público a bajar por URL.
     *
     * $context['channel_config'] (ver Notify::deliverOne / Models\Channel) puede
     * traer un servidor SMTP propio del tenant (smtp_host/port/encryption/
     * username/password) y from_name; sin eso usa el mailer del sistema.
     */
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        $config = $context['channel_config'] ?? [];
        $mailer = $this->applySmtpConfig($config);
        $fromName = $config['from_name'] ?? null;

        Mail::mailer($mailer)->html($body, function ($message) use ($address, $subject, $fromName) {
            $message->to($address)->subject($subject ?: '(sin asunto)');

            if ($fromName) {
                $message->from(config('mail.from.address'), $fromName);
            }
        });

        return '';
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
