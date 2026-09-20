<?php namespace Aero\Notify\Classes\Drivers;

use Aero\Notify\Models\PushSettings;
use Aero\Notify\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push (VAPID) a todos los dispositivos suscritos del usuario
 * (address = "user:ID"). Las suscripciones caducadas (404/410) se eliminan.
 */
class PushDriver implements ChannelDriverInterface
{
    public function send(string $address, ?string $subject, string $body, array $context = []): string
    {
        $userId = (int) substr($address, 5);

        $keys = PushSettings::keys();
        if (!$keys) {
            throw new \RuntimeException('Web Push sin claves VAPID: ejecuta `php artisan notify:vapid`.');
        }

        $subs = PushSubscription::where('user_id', $userId)->get();
        if ($subs->isEmpty()) {
            throw new SkipDelivery('no_subscription');
        }

        $webPush = new WebPush(['VAPID' => [
            'subject'    => $keys['subject'],
            'publicKey'  => $keys['public'],
            'privateKey' => $keys['private'],
        ]], ['TTL' => 86400, 'urgency' => 'high']);

        $payload = json_encode([
            'title'       => $subject ?: config('app.name'),
            'body'        => mb_strimwidth(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 180, '…'),
            'url'         => $context['url'] ?? null,
            'tag'         => $context['event_code'] ?? null,
            'delivery_id' => $context['delivery_id'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        $byEndpoint = $subs->keyBy('endpoint');

        foreach ($subs as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                ]),
                $payload
            );
        }

        $ok = 0;
        $lastError = null;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $ok++;
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $byEndpoint[$report->getEndpoint()]?->delete();
                continue;
            }

            $lastError = $report->getReason();
        }

        if ($ok === 0) {
            if ($lastError) {
                throw new \RuntimeException("Web Push: {$lastError}");
            }

            throw new SkipDelivery('no_subscription');
        }

        return (string) $ok;
    }
}
