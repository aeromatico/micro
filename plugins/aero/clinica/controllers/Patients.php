<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\Audit;
use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\ScopesToTenant;
use Aero\Clinica\Models\Patient;
use Backend\Classes\Controller;
use BackendMenu;

class Patients extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.clinica.use', 'aero.clinica.reception', 'aero.clinica.professional', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'patients');
    }

    /** Abrir la ficha queda en la bitácora. */
    public function update($recordId = null, $context = null)
    {
        $result = $this->asExtension('FormController')->update($recordId, $context);

        if ($m = $this->formGetModel()) {
            if ($m->exists) {
                Audit::record('view', $m->tenant_id, $m->id, 'patient', $m->id);
            }
        }

        return $result;
    }

    /** Desde la agenda: /patients/create?customer_id=… deja elegido al cliente. */
    public function formCreateModelObject()
    {
        $model = $this->asExtension('FormController')->formCreateModelObject();
        if ($cid = (int) get('customer_id')) {
            $model->customer_id = $cid;
        }

        return $model;
    }

    /** Abre una nota nueva (consulta o control) para este paciente. Solo personal clínico. */
    public function update_onStartNote($recordId = null)
    {
        $user = \BackendAuth::getUser();
        if (!$user->hasAccess(['aero.clinica.professional', 'aero.clinica.superadmin'], false)) {
            throw new \ApplicationException('Solo el personal clínico puede abrir notas.');
        }

        $patient = $this->scopeToTenant(Patient::whereKey((int) $recordId))->firstOrFail();
        $kind = post('kind') === 'control' ? 'control' : 'consultation';

        try {
            $note = app(\Aero\Clinica\Classes\ConsultationService::class)->start($patient, $kind, $user);
        } catch (\Aero\Clinica\Classes\ClinicaException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return \Backend::redirect('aero/clinica/consultations/update/' . $note->id);
    }

    // ---- Hoja de seguimiento y laboratorio manual (solo personal clínico) ----

    public function update_onAddLab($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        try {
            app(\Aero\Clinica\Classes\ClinicalItems::class)->addLab($p, [
                'analyte_id' => post('analyte_id'), 'analyte_name' => post('analyte_name'), 'value' => post('value'),
                'unit' => post('unit'), 'measured_on' => post('measured_on'), 'note' => post('note'),
                'ref_low' => post('ref_low'), 'ref_high' => post('ref_high'), 'lab_name' => post('lab_name'), 'order_item_id' => post('order_item_id'),
            ], \BackendAuth::getUser());
        } catch (\Aero\Clinica\Classes\ClinicaException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return $this->sheetUpdate($p);
    }

    public function update_onDeleteLab($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        \Aero\Clinica\Models\LabResult::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->findOrFail((int) post('lab_id'))->delete();

        return $this->sheetUpdate($p);
    }

    // ---- Vacunación, estudios, documentos y control (cada handler refresca su sección) ----

    public function update_onApplyVaccine($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        $this->orFail(fn () => app(\Aero\Clinica\Classes\VaccineService::class)->apply($p, [
            'vaccine_id' => post('vaccine_id'), 'vaccine_name' => post('vaccine_name'), 'dose_label' => post('dose_label'), 'applied_on' => post('applied_on'),
            'lot' => post('lot'), 'manufacturer' => post('manufacturer'), 'route_site' => post('route_site'), 'next_due_on' => post('next_due_on'), 'notes' => post('notes'),
        ], \BackendAuth::getUser()));

        return ['#mf-vaccines' => $this->makePartial('vaccines', ['p' => $p])];
    }

    public function update_onDeleteVaccination($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        \Aero\Clinica\Models\Vaccination::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->findOrFail((int) post('vaccination_id'))->delete();

        return ['#mf-vaccines' => $this->makePartial('vaccines', ['p' => $p])];
    }

    public function update_onUploadStudy($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        $file = \Request::file('study_file');
        if (!$file instanceof \Illuminate\Http\UploadedFile) {
            throw new \ApplicationException('Elija un archivo.');
        }

        $this->orFail(fn () => app(\Aero\Clinica\Classes\FileService::class)->attach($p, $file, [
            'kind' => post('kind'), 'title' => post('title'), 'taken_on' => post('taken_on'), 'consultation_id' => post('consultation_id'),
            'notes' => post('notes'), 'results' => (array) post('results', []),
        ], \BackendAuth::getUser()));
        \Flash::success('Archivo guardado (cifrado).');

        return $this->sheetUpdate($p) + ['#mf-studies' => $this->makePartial('studies', ['p' => $p])];
    }

    public function update_onDeleteFile($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        \Aero\Clinica\Models\ClinicaFile::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->findOrFail((int) post('file_id'))->delete();

        return ['#mf-studies' => $this->makePartial('studies', ['p' => $p])];
    }

    public function update_onGenerateDocument($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        $t = \Aero\Clinica\Models\DocumentTemplate::forTenant((int) $p->tenant_id)->where('is_active', true)->findOrFail((int) post('template_id'));
        $c = post('consultation_id') ? \Aero\Clinica\Models\Consultation::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->findOrFail((int) post('consultation_id')) : null;

        $doc = $this->orFail(fn () => app(\Aero\Clinica\Classes\DocumentService::class)->generate($p, $t, \BackendAuth::getUser(), $c));

        return \Backend::redirect('aero/clinica/documents/update/' . $doc->id);
    }

    public function update_onBookControl($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        $b = $this->orFail(fn () => \Aero\Clinica\Classes\Scheduling::bookControl($p, [
            'branch_id' => post('branch_id'), 'service_id' => post('service_id'), 'worker_id' => post('worker_id'),
            'starts_at' => post('starts_at'), 'reason' => post('reason'),
        ], \BackendAuth::getUser()));
        \Flash::success('Control agendado para el ' . $b->starts_at->format('d/m/Y H:i') . ' (reserva ' . $b->code . ').');

        return ['#mf-book' => $this->makePartial('book', ['p' => $p])];
    }

    protected function orFail(callable $fn)
    {
        try {
            return $fn();
        } catch (\Aero\Clinica\Classes\ClinicaException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    protected function sheetUpdate(Patient $p): array
    {
        return ['#mc-sheet' => $this->makePartial('sheet', ['p' => $p]), '#mc-labs' => $this->makePartial('labs', ['p' => $p])];
    }

    protected function clinicalPatient($id): Patient
    {
        if (!\BackendAuth::getUser()->hasAccess(['aero.clinica.professional', 'aero.clinica.superadmin'], false)) {
            throw new \ApplicationException('Solo el personal clínico puede ver o anotar resultados.');
        }

        return $this->scopeToTenant(Patient::whereKey((int) $id))->firstOrFail();
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el negocio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    public function formExtendFields($form): void
    {
        $model = $form->model;

        // Un registro existente no se puede mover de negocio desde la ficha.
        if ($model->exists && ($f = $form->getField('tenant_id'))) {
            $f->readOnly = true;
        }
    }

    public function listExtendColumns($list): void
    {
        if (!\Aero\Clinica\Classes\CustomerLink::available()) {
            $list->removeColumn('customer');
        }
    }
}
