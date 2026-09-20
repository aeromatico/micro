<?php namespace Aero\Notify\Models;

use Model;

/** Un navegador/dispositivo suscrito a Web Push. Único por endpoint. */
class PushSubscription extends Model
{
    public $table = 'aero_notify_push_subscriptions';

    protected $guarded = [];

    protected $dates = ['last_used_at'];

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /** Alta idempotente: reasigna el endpoint al usuario actual si ya existía. */
    public static function register(int $userId, int $tenantId, array $sub, ?string $userAgent = null): self
    {
        $endpoint = (string) ($sub['endpoint'] ?? '');
        $p256dh   = (string) ($sub['keys']['p256dh'] ?? '');
        $auth     = (string) ($sub['keys']['auth'] ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new \InvalidArgumentException('Suscripción push incompleta.');
        }

        return static::updateOrCreate(
            ['endpoint_hash' => static::hashEndpoint($endpoint)],
            [
                'user_id'      => $userId,
                'tenant_id'    => $tenantId,
                'endpoint'     => $endpoint,
                'p256dh'       => $p256dh,
                'auth'         => $auth,
                'user_agent'   => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'last_used_at' => now(),
            ]
        );
    }
}
