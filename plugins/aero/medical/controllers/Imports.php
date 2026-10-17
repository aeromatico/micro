<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\ImportService;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\ImportBatch;
use Aero\Medical\Models\MedicalFile;
use Aero\Medical\Models\Patient;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Importar historias anteriores: carga masiva y revisión humana de la asociación archivo↔paciente.
 * Quien administra (use/settings) puede asociar; leer el contenido de los archivos sigue exigiendo permiso clínico.
 */
class Imports extends Controller
{
    use ScopesToTenant;

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'imports');
    }

    public function index(): void
    {
        if (request()->ajax()) {
            return;
        }
        $this->pageTitle = 'Importar historias anteriores';
        $this->vars['batches'] = $this->scopeToTenant(ImportBatch::query())->orderByDesc('id')->limit(30)->get();
    }

    public function review($id = null): void
    {
        if (request()->ajax()) {
            return;
        }
        $batch = $this->scopeToTenant(ImportBatch::whereKey((int) $id))->firstOrFail();
        $this->pageTitle = 'Revisar importación';
        $this->vars['batch'] = $batch;
        $this->vars['canRead'] = BackendAuth::getUser()->hasAccess(['aero.medical.professional', 'aero.medical.superadmin'], false);
        $this->vars['files'] = MedicalFile::where('import_id', $batch->id)->orderBy('id')->get();
        $this->vars['patients'] = Patient::where('tenant_id', $batch->tenant_id)->orderBy('name')->limit(500)->get(['id', 'name', 'document']);
    }

    public function index_onUpload()
    {
        $tenantId = (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        $files = array_values(array_filter((array) \Request::file('files'), fn ($f) => $f instanceof \Illuminate\Http\UploadedFile));
        try {
            $r = app(ImportService::class)->receive($tenantId, $files, BackendAuth::getUser(), (string) post('label'));
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        $r['errors'] ? \Flash::warning("Se importaron {$r['imported']} archivo(s). No se importaron: " . implode(' · ', $r['errors'])) : \Flash::success("Se importaron {$r['imported']} archivo(s). Revise la asociación con cada paciente.");

        return $r['imported'] ? \Backend::redirect('aero/medical/imports/review/' . $r['batch']->id) : null;
    }

    public function review_onConfirm($id = null)
    {
        $batch = $this->scopeToTenant(ImportBatch::whereKey((int) $id))->firstOrFail();
        $f = MedicalFile::where('import_id', $batch->id)->findOrFail((int) post('file_id'));
        $p = $this->scopeToTenant(Patient::whereKey((int) post('patient_id')))->first();
        if (!$p) {
            throw new \ApplicationException('Elija un paciente.');
        }

        try {
            app(ImportService::class)->confirm($f, $p, BackendAuth::getUser());
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Archivo asociado a ' . $p->name . '.');

        return \Backend::redirect('aero/medical/imports/review/' . $batch->id);
    }

    public function review_onDiscard($id = null)
    {
        $batch = $this->scopeToTenant(ImportBatch::whereKey((int) $id))->firstOrFail();
        $f = MedicalFile::where('import_id', $batch->id)->findOrFail((int) post('file_id'));
        try {
            app(ImportService::class)->discard($f, BackendAuth::getUser());
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Archivo descartado.');

        return \Backend::redirect('aero/medical/imports/review/' . $batch->id);
    }
}
