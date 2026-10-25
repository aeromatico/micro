<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\ListExporter;
use Backend\Classes\Controller;
use BackendAuth;

/**
 * Entrega el archivo generado por ListExporter. Exige sesión del backend y el
 * archivo es del usuario que lo pidió (su id va en el nombre en disco).
 */
class ListExport extends Controller
{
    public function download($token = '')
    {
        $user = BackendAuth::getUser();
        $found = $user ? ListExporter::pathFor((string) $token, (int) $user->id) : null;
        if (!$found) {
            return response('El archivo ya no está disponible. Vuelve a exportar.', 404);
        }
        [$path, $name] = $found;

        return response()->download($path, $name ?: 'lista.csv')->deleteFileAfterSend(true);
    }
}
