<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\Patient;
use Backend\Classes\Controller;
use BackendMenu;

class Patients extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.medical.use', 'aero.medical.reception', 'aero.medical.professional', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'patients');
    }

    /** Sin modo elegido el módulo no abre: primero se configura (humano o animal). */
    public function index()
    {
        if (!CurrentTenant::isAdmin() && !Mode::forTenant(CurrentTenant::id())) {
            \Flash::info('Elija primero el modo del negocio: humano o animal.');

            return \Backend::redirect('aero/medical/settings');
        }

        $this->pageTitle = Mode::term('patients', Mode::forTenant(CurrentTenant::id()));
        $this->asExtension('ListController')->index();
    }

    public function create($context = null)
    {
        if (!CurrentTenant::isAdmin() && !Mode::forTenant(CurrentTenant::id())) {
            return \Backend::redirect('aero/medical/settings');
        }

        return $this->asExtension('FormController')->create($context);
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

    /** Desde la agenda: /patients/create?customer_id=… deja elegido al tutor. */
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
        if (!$user->hasAccess(['aero.medical.professional', 'aero.medical.superadmin'], false)) {
            throw new \ApplicationException('Solo el personal clínico puede abrir notas.');
        }

        $patient = $this->scopeToTenant(Patient::whereKey((int) $recordId))->firstOrFail();
        $kind = post('kind') === 'control' ? 'control' : 'consultation';

        try {
            $note = app(\Aero\Medical\Classes\ConsultationService::class)->start($patient, $kind, $user);
        } catch (\Aero\Medical\Classes\MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return \Backend::redirect('aero/medical/consultations/update/' . $note->id);
    }

    // ---- Hoja de seguimiento y laboratorio manual (solo personal clínico) ----

    public function update_onAddLab($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        try {
            app(\Aero\Medical\Classes\ClinicalItems::class)->addLab($p, [
                'analyte_id' => post('analyte_id'), 'analyte_name' => post('analyte_name'), 'value' => post('value'),
                'unit' => post('unit'), 'measured_on' => post('measured_on'), 'note' => post('note'),
            ], \BackendAuth::getUser());
        } catch (\Aero\Medical\Classes\MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return $this->sheetUpdate($p);
    }

    public function update_onDeleteLab($recordId = null)
    {
        $p = $this->clinicalPatient($recordId);
        \Aero\Medical\Models\LabResult::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->findOrFail((int) post('lab_id'))->delete();

        return $this->sheetUpdate($p);
    }

    protected function sheetUpdate(Patient $p): array
    {
        return ['#mc-sheet' => $this->makePartial('sheet', ['p' => $p]), '#mc-labs' => $this->makePartial('labs', ['p' => $p])];
    }

    protected function clinicalPatient($id): Patient
    {
        if (!\BackendAuth::getUser()->hasAccess(['aero.medical.professional', 'aero.medical.superadmin'], false)) {
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

    /** Campos y textos según el modo del paciente (o del negocio si es nuevo). */
    public function formExtendFields($form): void
    {
        $model = $form->model;
        $mode = $model->exists ? $model->kind : Mode::forTenant($model->tenant_id ?: CurrentTenant::id());
        $hidden = Mode::hiddenFields($mode);

        foreach ($hidden as $name) {
            $form->removeField($name);
        }

        // Humano: el contacto es de la propia persona; sin nombre aparte.
        if ($mode !== Mode::ANIMAL) {
            $form->removeField('contact_name');
        }

        if ($f = $form->getField('name')) {
            $f->label = Mode::term('name', $mode);
        }
        if ($f = $form->getField('customer_id')) {
            $f->label = 'Cliente de Reservas (' . mb_strtolower(Mode::term('guardian', $mode)) . ')';
        }
        if ($f = $form->getField('personal_history')) {
            $f->label = Mode::term('history', $mode);
        }

        // Un registro existente no se puede mover de negocio desde la ficha.
        if ($model->exists && ($f = $form->getField('tenant_id'))) {
            $f->readOnly = true;
        }
    }

    public function listExtendColumns($list): void
    {
        $mode = Mode::forTenant(CurrentTenant::id());

        if ($mode) {
            foreach (Mode::hiddenFields($mode) as $name) {
                $list->removeColumn($name);
            }
            if ($mode === Mode::HUMAN) {
                $list->removeColumn('species');
                $list->removeColumn('breed');
                $list->removeColumn('microchip');
            }
        }

        if (!\Aero\Medical\Classes\CustomerLink::available()) {
            $list->removeColumn('customer');
        }
    }
}
