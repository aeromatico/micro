<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Member;
use Illuminate\Support\Str;
use RainLab\User\Models\User;

/** Crea (o vincula) el usuario de RainLab.User de un miembro para que entre al portal con su correo. */
class ChurchUsers
{
    public bool $created = false;

    public function ensureUser(Member $m, ?string $resetUrl = null): User
    {
        $this->created = false;

        if ($m->user_id && ($user = User::find($m->user_id))) {
            return $user;
        }
        if (!$m->email) {
            throw new ChurchException('Necesita un correo para crear su acceso.');
        }
        if ($m->is_minor) {
            throw new ChurchException('Un menor no tiene acceso propio al portal.');
        }

        $user = User::where('email', $m->email)->where('is_guest', false)->first();
        if ($user && Member::where('tenant_id', $m->tenant_id)->where('user_id', $user->id)->where('id', '!=', $m->id)->exists()) {
            throw new ChurchException('Ese usuario ya está vinculado a otro miembro de la iglesia.');
        }

        if (!$user) {
            $password = Str::password(24);
            $user = new User();
            $user->first_name = $m->first_name;
            $user->last_name = $m->last_name;
            $user->email = $m->email;
            $user->username = $m->email;
            $user->password = $password;
            $user->password_confirmation = $password;
            $user->activated_at = now();
            $user->save();
            $this->created = true;
        }

        $m->user_id = $user->id;
        $m->save();

        if ($this->created && $resetUrl) {
            try {
                $user->setUrlForPasswordReset($resetUrl);
                $user->sendConfirmRegistrationNotification();
            } catch (\Throwable $e) {
                \Log::warning('[church] No se pudo enviar la invitación del portal: ' . $e->getMessage());
            }
        }

        return $user;
    }
}
