<?php namespace Aero\Church\Classes;

use BackendAuth;

/**
 * Un líder de ministerio (permiso `leader` sin ningún permiso de administración) solo ve
 * los grupos donde él es el responsable del panel. El resto de roles ve todos los de su iglesia.
 */
class GroupAccess
{
    /** Id del usuario del panel si está restringido a sus grupos; null si ve todos. */
    public static function restrictedUserId(): ?int
    {
        $user = BackendAuth::getUser();
        if (!$user) {
            return null; // servicio/job: quien llama ya fijó el alcance
        }

        foreach (['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'] as $p) {
            if ($user->hasAccess($p)) {
                return null;
            }
        }

        return $user->hasAccess('aero.church.leader') ? (int) $user->id : 0;
    }
}
