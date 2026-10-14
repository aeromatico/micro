<?php namespace Aero\Livechat\Classes;

use Illuminate\Support\Facades\Crypt;

/**
 * Identidad verificada para usuarios YA registrados (backend): el widget deja
 * de pedir nombre/correo/celular y el servidor confía en estos datos porque
 * los firmó la propia plataforma. Cifrado con la clave de la app + caducidad
 * corta: no sirve para suplantar a nadie ni para reutilizarse días después.
 */
class IdentityToken
{
    public const TTL_MINUTES = 15;

    /** @param array{name:string,email:string,phone?:?string,user_id?:?int,ticket?:?string} $identity */
    public static function make(array $identity): string
    {
        return Crypt::encryptString(json_encode([
            'name'    => (string) $identity['name'],
            'email'   => (string) $identity['email'],
            'phone'   => $identity['phone'] ?? null,
            'user_id' => $identity['user_id'] ?? null,
            'ticket'  => $identity['ticket'] ?? null,
            'exp'     => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ]));
    }

    public static function read(string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($data) || ($data['exp'] ?? 0) < now()->timestamp || empty($data['email']) || empty($data['name'])) {
            return null;
        }

        return $data;
    }
}
