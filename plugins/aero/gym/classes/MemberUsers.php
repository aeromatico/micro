<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\Member;
use Illuminate\Support\Str;
use RainLab\User\Models\User;

/** Vincula al socio con un usuario de RainLab.User (base del futuro portal). */
class MemberUsers
{
    public function ensureUser(Member $member): User
    {
        if ($member->user_id && ($user = User::find($member->user_id))) {
            return $user;
        }
        if (!$member->email) {
            throw new GymException('El socio necesita un correo para crear su usuario.');
        }

        $user = User::where('email', $member->email)->where('is_guest', false)->first();
        if ($user && Member::where('user_id', $user->id)->where('id', '!=', $member->id)->exists()) {
            throw new GymException('Ese usuario ya está vinculado a otro socio.');
        }

        if (!$user) {
            $password = Str::password(24);
            $parts = preg_split('/\s+/', trim($member->name), 2);
            $user = new User();
            $user->first_name = $parts[0];
            $user->last_name = $parts[1] ?? null;
            $user->email = $member->email;
            $user->username = $member->email;
            $user->password = $password;
            $user->password_confirmation = $password;
            $user->activated_at = now();
            $user->save();
        }

        $member->user_id = $user->id;
        $member->save();

        return $user;
    }
}
