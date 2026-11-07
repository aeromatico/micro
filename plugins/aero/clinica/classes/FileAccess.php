<?php namespace Aero\Clinica\Classes;

use Aero\Clinica\Models\ClinicaFile;

/**
 * Quién puede leer qué archivo. El expediente clínico (laboratorio, imágenes, historias, adjuntos) es solo del
 * personal clínico.
 */
class FileAccess
{
    public static function canRead($user, ClinicaFile $f): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->hasAccess(['aero.clinica.professional', 'aero.clinica.superadmin'], false)) {
            return true;
        }

        return false;
    }
}
