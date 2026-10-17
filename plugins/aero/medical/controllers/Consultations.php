<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\ConsultationService;
use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Classes\VitalsSchema;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Professional;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Notas clínicas. Solo personal con acceso clínico (aero.medical.professional):
 * recepción no entra aquí. Pantalla propia (no FormController) por el
 * autoguardado, la firma y las adendas.
 */
class Consultations extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'consultations');
    }

    /** Vista y edición de una nota. */
    public function update($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->pageTitle = $c->kind_label . ' — ' . $c->patient->name;
        $this->vars['c'] = $c;
        $this->vars['mode'] = $c->patient->kind;
        $this->vars['editable'] = !$c->isSigned() && (int) $c->user_id === (int) BackendAuth::getUser()->id;
        $this->vars['vitalFields'] = VitalsSchema::fields($c->patient->kind);
        $this->vars['previous'] = $this->previousNote($c);

        Audit::record('view', $c->tenant_id, $c->patient_id, 'consultation', $c->id);
    }

    /** Impresión con firma y sello del profesional. */
    public function print($id = null)
    {
        $c = $this->noteOrFail($id);
        if (!$c->isSigned()) {
            throw new \ApplicationException('Solo se imprimen notas firmadas.');
        }

        $profile = Professional::where('tenant_id', $c->tenant_id)->where('user_id', $c->signed_by)->first();
        Audit::record('print', $c->tenant_id, $c->patient_id, 'consultation', $c->id);

        return \Response::make($this->makePartial('print', [
            'c'          => $c,
            'patient'    => $c->patient,
            'profile'    => $profile,
            'signature'  => $profile?->imageDataUri('signature'),
            'seal'       => $profile?->imageDataUri('seal'),
            'vitalFields' => VitalsSchema::fields($c->patient->kind),
            'business'   => $this->businessName((int) $c->tenant_id),
            'integrity'  => $c->integrityOk(),
        ]));
    }

    public function update_onAutosave($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ConsultationService::class)->autosave($c, (array) post('content', []), (array) post('vitals', []), BackendAuth::getUser()));

        return ['saved' => now()->format('H:i:s'), 'bmi' => $c->fresh('vitals')->vitals?->bmi];
    }

    public function update_onSign($id = null)
    {
        $c = $this->noteOrFail($id);
        $user = BackendAuth::getUser();
        $svc = app(ConsultationService::class);

        // Lo escrito en pantalla se guarda antes de firmar.
        $this->guard(fn () => $svc->autosave($c, (array) post('content', []), (array) post('vitals', []), $user));
        $r = $this->guard(fn () => $svc->sign($c->fresh(), $user));

        $r['warning'] ? \Flash::warning($r['warning']) : \Flash::success('Nota firmada. Ya no se puede modificar; use adendas para cambios.');

        return \Backend::redirect('aero/medical/consultations/update/' . $c->id);
    }

    public function update_onAddendum($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ConsultationService::class)->addendum($c, (string) post('addendum'), BackendAuth::getUser()));
        \Flash::success('Adenda agregada.');

        return \Backend::redirect('aero/medical/consultations/update/' . $c->id);
    }

    public function update_onDiscard($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ConsultationService::class)->discard($c, BackendAuth::getUser()));
        \Flash::success('Borrador descartado.');

        return \Backend::redirect('aero/medical/patients/update/' . $c->patient_id);
    }

    protected function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }

    protected function noteOrFail($id): Consultation
    {
        return $this->scopeToTenant(Consultation::with('patient')->whereKey((int) $id))->firstOrFail();
    }

    /** Lo anotado la vez anterior: última nota firmada del paciente, anterior a esta. */
    protected function previousNote(Consultation $c): ?Consultation
    {
        return Consultation::with('vitals')
            ->where('tenant_id', $c->tenant_id)->where('patient_id', $c->patient_id)
            ->where('status', 'signed')->where('id', '!=', $c->id)
            ->where('consulted_at', '<=', $c->consulted_at)
            ->orderByDesc('consulted_at')->orderByDesc('id')->first();
    }

    protected function businessName(int $tenantId): string
    {
        if (class_exists(\Aero\Office\Models\OfficeSettings::class)) {
            $n = \Aero\Office\Models\OfficeSettings::where('tenant_id', $tenantId)->value('business_name');
            if ($n) {
                return $n;
            }
        }

        return class_exists(\Aero\Sites\Models\Tenant::class) ? (string) \Aero\Sites\Models\Tenant::where('id', $tenantId)->value('name') : '';
    }
}
