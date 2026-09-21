<?php namespace Aero\Chat\Models;

use Model;

class ChatToken extends Model
{
    public $table = 'aero_chat_tokens';

    public $fillable = ['tenant_id', 'user_id', 'token_hash', 'device', 'last_used_at', 'expires_at'];

    protected $dates = ['last_used_at', 'expires_at'];

    public const TTL_DAYS = 3650; // deslizante: cada uso lo renueva, así la sesión dura hasta cerrar sesión

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Devuelve el token en claro; en la base solo queda su hash. */
    public static function issue(int $tenantId, int $userId, ?string $device = null): string
    {
        $plain = bin2hex(random_bytes(32));

        static::create([
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'token_hash' => static::hash($plain),
            'device'     => $device ? mb_substr($device, 0, 120) : null,
            'expires_at' => now()->addDays(static::TTL_DAYS),
        ]);

        return $plain;
    }
}
