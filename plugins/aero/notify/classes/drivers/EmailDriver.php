<?php namespace Aero\Notify\Classes\Drivers;

use Mail;

class EmailDriver implements ChannelDriverInterface
{
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        Mail::html($body, function ($message) use ($address, $subject) {
            $message->to($address)->subject($subject ?: '(sin asunto)');
        });

        return '';
    }
}
