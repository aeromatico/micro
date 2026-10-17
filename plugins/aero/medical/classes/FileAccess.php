<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\MedicalFile;

/**
 * Quién puede leer qué archivo. El expediente clínico (laboratorio, imágenes, historias, adjuntos) es solo del
 * personal clínico. Las fotos antes/después de peluquería no son clínicas: también las ve quien atiende
 * recepción o administra, porque son quienes las suben.
 */
class FileAccess
{
    public static function canRead($user, MedicalFile $f): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->hasAccess(['aero.medical.professional', 'aero.medical.superadmin'], false)) {
            return true;
        }

        return $f->isGroomingPhoto() && $user->hasAccess(['aero.medical.reception', 'aero.medical.use', 'aero.medical.settings'], false);
    }
}
