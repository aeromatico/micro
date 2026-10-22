<?php namespace Aero\Realestate\Classes;

use Illuminate\Support\Str;
use RainLab\User\Models\User;

/**
 * Vincula clientes, propietarios y agentes (cualquier modelo con name/email/user_id)
 * con un usuario de RainLab.User: ahí se pueblan todos y comparten un solo login.
 */
class PersonUsers
{
    /** true si la última llamada a ensureUser() creó el usuario (no solo lo vinculó). */
    public bool $created = false;

    /** Con $resetUrl, un usuario NUEVO recibe la invitación de RainLab para crear su contraseña (nunca se inicia sesión por él). */
    public function ensureUser($person, ?string $resetUrl = null): User
    {
        $this->created = false;

        if ($person->user_id && ($user = User::find($person->user_id))) {
            return $user;
        }
        if (!$person->email) {
            throw new RealestateException('Necesita un correo para crear su usuario.');
        }

        $user = User::where('email', $person->email)->where('is_guest', false)->first();
        if ($user && $person::where('tenant_id', $person->tenant_id)->where('user_id', $user->id)->where('id', '!=', $person->id)->exists()) {
            throw new RealestateException('Ese usuario ya está vinculado a otro registro de la inmobiliaria.');
        }

        if (!$user) {
            $password = Str::password(24);
            $parts = preg_split('/\s+/', trim($person->name), 2);
            $user = new User();
            $user->first_name = $parts[0];
            $user->last_name = $parts[1] ?? null;
            $user->email = $person->email;
            $user->username = $person->email;
            $user->password = $password;
            $user->password_confirmation = $password;
            $user->activated_at = now();
            $user->save();
            $this->created = true;
        }

        $person->user_id = $user->id;
        $person->save();

        if ($this->created && $resetUrl) {
            try {
                $user->setUrlForPasswordReset($resetUrl);
                $user->sendConfirmRegistrationNotification();
            } catch (\Throwable $e) {
                \Log::warning('[realestate] No se pudo enviar la invitación de cuenta: ' . $e->getMessage());
            }
        }

        return $user;
    }
}
