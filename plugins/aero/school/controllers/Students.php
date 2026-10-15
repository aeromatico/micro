<?php namespace Aero\School\Controllers;

use Aero\School\Classes\CurrentTenant;
use Aero\School\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Students extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'students');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el colegio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    protected function formGuardianInput(): array
    {
        $d = (array) post('Student', []);

        return [
            'id'           => (int) ($d['guardian_id'] ?? 0),
            'name'         => trim((string) ($d['guardian_name'] ?? '')),
            'phone'        => trim((string) ($d['guardian_phone'] ?? '')),
            'email'        => trim((string) ($d['guardian_email'] ?? '')),
            'relationship' => trim((string) ($d['guardian_relationship'] ?? '')) ?: null,
        ];
    }

    /** Vincula (o crea) el apoderado indicado en la pestaña «Apoderados». */
    public function formAfterSave($model): void
    {
        $in = $this->formGuardianInput();
        $guardian = null;

        if ($in['id']) {
            $guardian = \Aero\School\Models\Guardian::visible()->find($in['id']);
        } elseif ($in['name'] !== '') {
            $guardian = \Aero\School\Models\Guardian::firstOrNew(
                ['tenant_id' => $model->tenant_id, 'phone' => $in['phone'] ?: null, 'name' => $in['name']]);
            $guardian->email = $in['email'] ?: $guardian->email;
            $guardian->save();
        }

        if ($guardian && $guardian->tenant_id === $model->tenant_id) {
            $isFirst = !$model->guardians()->exists();
            $model->guardians()->syncWithoutDetaching([$guardian->id => [
                'relationship' => $in['relationship'], 'is_primary' => $isFirst, 'is_financial' => true,
            ]]);
        }
    }

    public function update_onRemoveGuardian($recordId = null)
    {
        $student = $this->formFindModelObject($recordId);
        $student->guardians()->detach((int) post('guardian_id'));
        \Flash::success('Apoderado desvinculado.');

        return \Redirect::refresh();
    }

    public function update_onGenerateCharges($recordId = null)
    {
        $student = $this->formFindModelObject($recordId);
        $n = app(\Aero\School\Classes\Billing::class)->generateForStudent($student);
        \Flash::success($n ? "Se generaron {$n} cuotas." : 'No había cuotas nuevas por generar.');

        return \Redirect::refresh();
    }
}
