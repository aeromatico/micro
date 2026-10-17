<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\ClinicalItems;
use Aero\Medical\Classes\ConsultationService;
use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Classes\VitalsSchema;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Diagnosis;
use Aero\Medical\Models\ExamCatalog;
use Aero\Medical\Models\Liname;
use Aero\Medical\Models\Medication;
use Aero\Medical\Models\Order;
use Aero\Medical\Models\Prescription;
use Aero\Medical\Models\Professional;
use Aero\Medical\Models\Questionnaire;
use Aero\Medical\Models\QuestionnaireAnswer;
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
        if (request()->ajax()) {
            return; // October corre la acción antes de cada handler: ni preparar ni auditar «abrió» en cada autoguardado
        }

        $c = $this->noteOrFail($id);
        $this->pageTitle = $c->kind_label . ' — ' . $c->patient->name;
        $this->prepare($c);

        Audit::record('view', $c->tenant_id, $c->patient_id, 'consultation', $c->id);
    }

    /** Variables que usan la página y sus parciales (también los refrescos por AJAX). */
    protected function prepare(Consultation $c): void
    {
        $c->unsetRelation('diagnoses');
        $this->vars['c'] = $c;
        $this->vars['mode'] = $c->patient->kind;
        $this->vars['editable'] = !$c->isSigned() && (int) $c->user_id === (int) BackendAuth::getUser()->id;
        $this->vars['vitalFields'] = VitalsSchema::fields($c->patient->kind);
        $this->vars['previous'] = $this->previousNote($c);
        $this->vars['dxList'] = Diagnosis::where('consultation_id', $c->id)->orderBy('position')->orderBy('id')->get();
        $this->vars['rx'] = Prescription::with('items')->where('consultation_id', $c->id)->first();
        $this->vars['order'] = Order::with('items')->where('consultation_id', $c->id)->first();
        $this->vars['answers'] = QuestionnaireAnswer::where('consultation_id', $c->id)->orderBy('id')->get();
        $this->vars['questionnaires'] = Questionnaire::forTenant((int) $c->tenant_id)->where('is_active', true)->orderBy('name')->get();
        $this->vars['exams'] = $this->examOptions((int) $c->tenant_id);
        $this->vars['dxResults'] = ['frequent' => collect(), 'cie' => collect(), 'custom' => collect()];
        $this->vars['medResults'] = collect();
        $this->vars['openQuestionnaire'] = null;
    }

    /** Parciales de la página por nombre → selector que actualizan. */
    protected const PARTIALS = [
        'dx' => '#mc-dx', 'dxresults' => '#mc-dx-results', 'medresults' => '#mc-med-results', 'rx' => '#mc-rx',
        'orders' => '#mc-orders', 'quest' => '#mc-quest', 'questform' => '#mc-quest-form',
    ];

    protected function refresh(Consultation $c, array $parts, array $vars = []): array
    {
        $this->prepare($c);
        $this->vars = $vars + $this->vars;

        $out = [];
        foreach ($parts as $p) {
            $out[self::PARTIALS[$p]] = $this->makePartial($p);
        }

        return $out;
    }

    protected function examOptions(int $tenantId)
    {
        ExamCatalog::ensureDefaults($tenantId);

        return ExamCatalog::forTenant($tenantId)->where('is_active', true)->orderBy('sort')->orderBy('name')->get();
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
            'dxList'     => Diagnosis::where('consultation_id', $c->id)->orderBy('position')->orderBy('id')->get(),
            'answers'    => QuestionnaireAnswer::where('consultation_id', $c->id)->orderBy('id')->get(),
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

    // ---- Diagnósticos, receta, órdenes y cuestionarios (cada handler refresca su parcial) ----

    public function update_onSearchDx($id = null)
    {
        $c = $this->noteOrFail($id);

        return $this->refresh($c, ['dxresults'], ['searched' => true, 'dxResults' => app(ClinicalItems::class)->searchDiagnoses((int) $c->tenant_id, $c->patient->kind, (string) post('dx_q'))]);
    }

    public function update_onAddDx($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ClinicalItems::class)->addDiagnosis($c, [
            'source' => post('source'), 'code' => post('code'), 'catalog_id' => post('catalog_id'), 'label' => post('label'),
            'certainty' => post('certainty'), 'primary' => (bool) post('primary'),
        ], BackendAuth::getUser()));
        return $this->refresh($c, ['dx', 'dxresults']);
    }

    public function update_onSetPrimaryDx($id = null)
    {
        $c = $this->noteOrFail($id);
        $d = Diagnosis::where('consultation_id', $c->id)->findOrFail((int) post('dx_id'));
        $this->guard(fn () => app(ClinicalItems::class)->setPrimary($c, $d, BackendAuth::getUser()));
        return $this->refresh($c, ['dx', 'dxresults']);
    }

    public function update_onSetCertaintyDx($id = null)
    {
        $c = $this->noteOrFail($id);
        $d = Diagnosis::where('consultation_id', $c->id)->findOrFail((int) post('dx_id'));
        $this->guard(fn () => app(ClinicalItems::class)->setCertainty($c, $d, (string) post('certainty'), BackendAuth::getUser()));
        return $this->refresh($c, ['dx', 'dxresults']);
    }

    public function update_onRemoveDx($id = null)
    {
        $c = $this->noteOrFail($id);
        $d = Diagnosis::where('consultation_id', $c->id)->findOrFail((int) post('dx_id'));
        $this->guard(fn () => app(ClinicalItems::class)->removeDiagnosis($c, $d, BackendAuth::getUser()));
        return $this->refresh($c, ['dx', 'dxresults']);
    }

    public function update_onSearchMed($id = null)
    {
        $c = $this->noteOrFail($id);
        $q = (string) post('med_q');
        $fmt = fn ($src) => fn ($m) => ['source' => $src, 'id' => $m->id, 'text' => trim($m->name . ' ' . $m->concentration . ($m->form ? ' (' . $m->form . ')' : ''))];
        $res = $c->patient->kind === Mode::ANIMAL
            ? Medication::search((int) $c->tenant_id, $q)->map($fmt('custom'))
            : Liname::search($q)->map($fmt('liname'))->concat(Medication::search((int) $c->tenant_id, $q, 8)->map($fmt('custom')));

        return $this->refresh($c, ['medresults'], ['medResults' => $res]);
    }

    public function update_onAddRx($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ClinicalItems::class)->addPrescriptionItem($c, [
            'source' => post('rx_source'), 'ref_id' => post('rx_ref'), 'medication' => post('medication'), 'dose' => post('dose'), 'route' => post('route'),
            'frequency' => post('frequency'), 'duration' => post('duration'), 'quantity' => post('quantity'), 'instructions' => post('instructions'),
        ], BackendAuth::getUser()));
        return $this->refresh($c, ['rx', 'medresults']);
    }

    public function update_onRemoveRx($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ClinicalItems::class)->removePrescriptionItem($c, (int) post('item_id'), BackendAuth::getUser()));
        return $this->refresh($c, ['rx', 'medresults']);
    }

    public function update_onAddOrder($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ClinicalItems::class)->addOrderItem($c, [
            'catalog_id' => post('catalog_id'), 'exam_name' => post('exam_name'), 'instructions' => post('exam_instructions'),
        ], BackendAuth::getUser()));
        return $this->refresh($c, ['orders']);
    }

    public function update_onRemoveOrder($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ClinicalItems::class)->removeOrderItem($c, (int) post('item_id'), BackendAuth::getUser()));
        return $this->refresh($c, ['orders']);
    }

    public function update_onOpenQuest($id = null)
    {
        $c = $this->noteOrFail($id);
        $q = Questionnaire::forTenant((int) $c->tenant_id)->where('is_active', true)->findOrFail((int) post('questionnaire_id'));

        return $this->refresh($c, ['questform'], ['openQuestionnaire' => $q]);
    }

    public function update_onAnswerQuest($id = null)
    {
        $c = $this->noteOrFail($id);
        $q = Questionnaire::forTenant((int) $c->tenant_id)->where('is_active', true)->findOrFail((int) post('questionnaire_id'));
        $this->guard(fn () => app(ClinicalItems::class)->answerQuestionnaire($c, $q, (array) post('a', []), BackendAuth::getUser()));
        return $this->refresh($c, ['quest', 'questform']);
    }

    public function update_onRemoveQuest($id = null)
    {
        $c = $this->noteOrFail($id);
        $this->guard(fn () => app(ClinicalItems::class)->removeAnswer($c, (int) post('answer_id'), BackendAuth::getUser()));
        return $this->refresh($c, ['quest', 'questform']);
    }

    // ---- Impresión de receta y órdenes ----

    public function printrx($id = null)
    {
        return $this->printDocument($id, 'rx');
    }

    public function printorders($id = null)
    {
        return $this->printDocument($id, 'orders');
    }

    protected function printDocument($id, string $which)
    {
        $c = $this->noteOrFail($id);
        if (!$c->isSigned()) {
            throw new \ApplicationException('Solo se imprimen documentos de notas firmadas.');
        }

        $doc = $which === 'rx' ? Prescription::with('items')->where('consultation_id', $c->id)->first() : Order::with('items')->where('consultation_id', $c->id)->first();
        if (!$doc || !count($doc->items)) {
            throw new \ApplicationException($which === 'rx' ? 'La nota no tiene receta.' : 'La nota no tiene órdenes.');
        }

        $profile = Professional::where('tenant_id', $c->tenant_id)->where('user_id', $c->signed_by)->first();
        Audit::record('print', $c->tenant_id, $c->patient_id, $which === 'rx' ? 'prescription' : 'order', $doc->id);

        return \Response::make($this->makePartial('print_' . $which, [
            'c' => $c, 'patient' => $c->patient, 'doc' => $doc, 'profile' => $profile,
            'signature' => $profile?->imageDataUri('signature'), 'seal' => $profile?->imageDataUri('seal'),
            'business' => $this->businessName((int) $c->tenant_id), 'integrity' => $c->integrityOk(),
        ]));
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
