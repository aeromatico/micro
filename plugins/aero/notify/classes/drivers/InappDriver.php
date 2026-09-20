<?php namespace Aero\Notify\Classes\Drivers;

use Aero\Notify\Models\InboxMessage;

/** Canal 'inapp': guarda el mensaje en la bandeja del usuario (address = "user:ID"). */
class InappDriver implements ChannelDriverInterface
{
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        $userId = (int) substr($address, 5);

        if (!$userId) {
            throw new SkipDelivery('no_user');
        }

        $message = InboxMessage::create([
            'user_id'     => $userId,
            'tenant_id'   => (int) ($context['tenant_id'] ?? 0),
            'delivery_id' => $context['delivery_id'] ?? null,
            'event_code'  => $context['event_code'] ?? null,
            'title'       => $subject,
            'body'        => trim(strip_tags($body)),
            'url'         => $context['url'] ?? null,
        ]);

        return (string) $message->id;
    }
}
