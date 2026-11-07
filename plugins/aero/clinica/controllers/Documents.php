<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\Audit;
use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\DocumentService;
use Aero\Clinica\Classes\ClinicaException;
use Aero\Clinica\Classes\ScopesToTenant;
use Aero\Clinica\Models\ClinicaDocument;
use Aero\Clinica\Models\Professional;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** Ver, editar (borrador), emitir con firma y sello e imprimir documentos del paciente. Solo personal clínico. */
class Documents extends Controller
{
    use ScopesToTenant;

    public $requiredPermissions = ['aero.clinica.professional', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'patients');
    }

    public function update($id = null)
    {
        if (request()->ajax()) {
            return; // October corre la acción antes de cada handler
        }

        $d = $this->docOrFail($id);
        $this->pageTitle = $d->title;
        $this->vars['d'] = $d;
        $this->vars['editable'] = !$d->isIssued() && (int) $d->user_id === (int) BackendAuth::getUser()->id;
        Audit::record('view', $d->tenant_id, $d->patient_id, 'document', $d->id);
    }

    public function print($id = null)
    {
        $d = $this->docOrFail($id);
        if (!$d->isIssued()) {
            throw new \ApplicationException('Solo se imprimen documentos emitidos.');
        }

        $profile = Professional::where('tenant_id', $d->tenant_id)->where('user_id', $d->user_id)->first();
        Audit::record('print', $d->tenant_id, $d->patient_id, 'document', $d->id);

        return \Response::make($this->makePartial('print', [
            'd' => $d, 'patient' => $d->patient, 'profile' => $profile, 'integrity' => $d->integrityOk(),
            'signature' => $profile?->imageDataUri('signature'), 'seal' => $profile?->imageDataUri('seal'),
            'business' => \Aero\Clinica\Classes\DocumentRenderer::businessName((int) $d->tenant_id),
        ]));
    }

    public function update_onSave($id = null)
    {
        $d = $this->docOrFail($id);
        $this->guard(fn () => app(DocumentService::class)->update($d, (string) post('title'), (string) post('body'), BackendAuth::getUser()));
        \Flash::success('Borrador guardado.');
    }

    public function update_onIssue($id = null)
    {
        $d = $this->docOrFail($id);
        $svc = app(DocumentService::class);
        $this->guard(function () use ($svc, $d) {
            $svc->update($d, (string) post('title'), (string) post('body'), BackendAuth::getUser());
            $svc->issue($d->fresh(), BackendAuth::getUser());
        });
        \Flash::success('Documento emitido. Ya no se puede modificar.');

        return \Backend::redirect('aero/clinica/documents/update/' . $d->id);
    }

    public function update_onDiscard($id = null)
    {
        $d = $this->docOrFail($id);
        $this->guard(fn () => app(DocumentService::class)->discard($d, BackendAuth::getUser()));
        \Flash::success('Borrador descartado.');

        return \Backend::redirect('aero/clinica/patients/update/' . $d->patient_id);
    }

    protected function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (ClinicaException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    protected function docOrFail($id): ClinicaDocument
    {
        return $this->scopeToTenant(ClinicaDocument::with('patient')->whereKey((int) $id))->firstOrFail();
    }
}
