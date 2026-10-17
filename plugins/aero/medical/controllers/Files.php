<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\MedicalFile;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Único camino para leer un archivo clínico: permiso clínico + tenant + bitácora. El contenido está cifrado
 * en disco; aquí se descifra y se sirve con cabeceras que impiden que el navegador lo interprete como página.
 */
class Files extends Controller
{
    use ScopesToTenant;

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'patients');
    }

    public function download($id = null)
    {
        $f = $this->scopeToTenant(MedicalFile::whereKey((int) $id))->firstOrFail();

        // Un archivo de importación sin asociar solo lo ve quien revisa la importación (no está en ninguna ficha).
        try {
            $data = $f->contents();
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        Audit::record('download', $f->tenant_id, $f->patient_id, 'file', $f->id);

        $name = str_replace(['"', "\r", "\n"], '', (string) $f->original_name);
        $inline = $f->isInline() && request('descargar') === null;

        return \Response::make($data, 200, [
            'Content-Type'            => $f->mime,
            'Content-Disposition'     => ($inline ? 'inline' : 'attachment') . '; filename="' . addcslashes($name, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'Cache-Control'           => 'private, no-store',
            'Content-Length'          => (string) strlen($data),
        ]);
    }
}
