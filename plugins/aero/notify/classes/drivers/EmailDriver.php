<?php namespace Aero\Notify\Classes\Drivers;

use Mail;

class EmailDriver implements ChannelDriverInterface
{
    /**
     * $context puede traer un adjunto: `attachment_binary` (+ opcional
     * `attachment_filename`/`attachment_mime`) para datos en memoria, o
     * `attachment_url` para un archivo público a bajar por URL.
     */
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        Mail::html($body, function ($message) use ($address, $subject, $context) {
            $message->to($address)->subject($subject ?: '(sin asunto)');

            if (!empty($context['attachment_binary'])) {
                $message->attachData(
                    $context['attachment_binary'],
                    $context['attachment_filename'] ?? 'adjunto',
                    array_filter(['mime' => $context['attachment_mime'] ?? null])
                );
            } elseif (!empty($context['attachment_url'])) {
                $message->attach($context['attachment_url']);
            }
        });

        return '';
    }
}
