<?php namespace Aero\School\Classes;

use Aero\School\Models\Guardian;
use Illuminate\Support\Str;
use RainLab\User\Models\User;

/** Vincula al apoderado con un usuario de RainLab.User (acceso al portal del padre). */
class GuardianUsers
{
    public function ensureUser(Guardian $guardian): User
    {
        if ($guardian->user_id && ($user = User::find($guardian->user_id))) {
            return $user;
        }
        if (!$guardian->email) {
            throw new SchoolException('El apoderado necesita un correo para crear su usuario.');
        }

        $user = User::where('email', $guardian->email)->where('is_guest', false)->first();
        if ($user && Guardian::where('user_id', $user->id)->where('id', '!=', $guardian->id)->exists()) {
            throw new SchoolException('Ese usuario ya está vinculado a otro apoderado.');
        }

        if (!$user) {
            $password = Str::password(24);
            $parts = preg_split('/\s+/', trim($guardian->name), 2);
            $user = new User();
            $user->first_name = $parts[0];
            $user->last_name = $parts[1] ?? null;
            $user->email = $guardian->email;
            $user->username = $guardian->email;
            $user->password = $password;
            $user->password_confirmation = $password;
            $user->activated_at = now();
            $user->save();
        }

        $guardian->user_id = $user->id;
        $guardian->save();

        return $user;
    }

    /** Apoderados del tenant vinculados al usuario que inició sesión en el portal. */
    public static function forUser(?User $user, ?int $tenantId)
    {
        if (!$user || !$tenantId) {
            return collect();
        }

        return Guardian::where('user_id', $user->id)->where('tenant_id', $tenantId)->get();
    }
}
