<?php namespace Aero\Church\Classes;

use BackendAuth;

/**
 * Dirección pastoral (use, pastor, superadmin) ve todas las peticiones de su iglesia; un responsable
 * (permiso `leader`) solo las que se le asignaron.
 */
class PrayerAccess
{
    /** Id del usuario si está restringido a lo asignado; null si ve todas; 0 si no ve ninguna. */
    public static function restrictedUserId(): ?int
    {
        $user = BackendAuth::getUser();
        if (!$user) {
            return null; // servicio/formulario público: quien llama ya fijó el alcance
        }

        foreach (['aero.church.use', 'aero.church.pastor', 'aero.church.superadmin'] as $p) {
            if ($user->hasAccess($p)) {
                return null;
            }
        }

        return $user->hasAccess('aero.church.leader') ? (int) $user->id : 0;
    }
}
