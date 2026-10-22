<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\CurrentTenant;
use Aero\Realestate\Classes\AgreementService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Agreements extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'agreements');
    }

    protected function agreement(): \Aero\Realestate\Models\Agreement
    {
        return \Aero\Realestate\Models\Agreement::visible()->findOrFail((int) post('id'));
    }

    /** Aprobar, firmar, reabrir, renovar y finalizar son de la administración; el agente prepara borradores. */
    protected function requireAdmin(): void
    {
        if (!(CurrentTenant::isAdmin() || \BackendAuth::getUser()?->hasAccess('aero.realestate.use'))) {
            throw new \ApplicationException('Esta acción es de la administración de la inmobiliaria.');
        }
    }

    protected function act(callable $fn, string $ok, bool $admin = false)
    {
        $admin && $this->requireAdmin();
        try {
            $fn(app(AgreementService::class), $this->agreement());
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success($ok);

        return \Redirect::refresh();
    }

    public function formExtendModel($model)
    {
        if (!$model->exists) {
            foreach (['property_id', 'client_id', 'owner_id', 'offer_id', 'lead_id', 'contract_id'] as $k) {
                get($k) && $model->{$k} = (int) get($k);
            }
            get('type') && $model->type = (string) get('type');
        }

        return $model;
    }

    /** Generar pasa por el servicio: rellena el texto desde la plantilla y deja el contrato en borrador. */
    public function create_onSave($context = null)
    {
        $d = (array) post('Agreement');
        try {
            \Aero\Realestate\Models\Property::visible()->findOrFail((int) ($d['property_id'] ?? 0));
            foreach (['client_id' => \Aero\Realestate\Models\Client::class, 'owner_id' => \Aero\Realestate\Models\Owner::class, 'agent_id' => \Aero\Realestate\Models\Agent::class, 'offer_id' => \Aero\Realestate\Models\Offer::class, 'contract_id' => \Aero\Realestate\Models\Contract::class] as $k => $cls) {
                if (($d[$k] ?? '') !== '') {
                    $cls::visible()->findOrFail((int) $d[$k]);
                }
            }
            $agreement = app(AgreementService::class)->generate([
                'type' => $d['type'] ?? null, 'template_id' => (int) ($d['template_id'] ?? 0), 'property_id' => (int) $d['property_id'], 'title' => ($d['title'] ?? '') !== '' ? $d['title'] : null,
                'offer_id' => $d['offer_id'] ?? null, 'client_id' => $d['client_id'] ?? null, 'owner_id' => $d['owner_id'] ?? null, 'agent_id' => $d['agent_id'] ?? null,
                'contract_id' => $d['contract_id'] ?? null, 'amount' => $d['amount'] ?? null, 'currency' => $d['currency'] ?? null, 'total_price' => $d['total_price'] ?? null,
                'deposit' => $d['deposit'] ?? null, 'term_months' => $d['term_months'] ?? null, 'start_date' => $d['start_date'] ?? null, 'end_date' => $d['end_date'] ?? null,
                'variables' => (array) ($d['variables'] ?? []),
            ]);
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Borrador generado: revísalo y ajústalo.');

        return \Redirect::to(\Backend::url('aero/realestate/agreements/update/' . $agreement->id));
    }

    /** PDF del contrato; sin dompdf, versión HTML imprimible. */
    public function pdf($id = null)
    {
        $a = \Aero\Realestate\Models\Agreement::visible()->findOrFail((int) $id);
        $maker = new \Aero\Realestate\Classes\AgreementPdf();
        $pdf = $maker->pdf($a);

        return $pdf === null ? response($maker->html($a)) : response($pdf, 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . \Aero\Realestate\Classes\AgreementPdf::filename($a) . '"',
        ]);
    }

    /** Vista previa HTML en una página aislada: el texto lo edita el usuario, así que no puede ejecutar nada. */
    public function preview($id = null)
    {
        $a = \Aero\Realestate\Models\Agreement::visible()->findOrFail((int) $id);

        return response((new \Aero\Realestate\Classes\AgreementPdf())->html($a), 200, [
            'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'", 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update_onSignForm() { $this->requireAdmin(); return $this->makePartial('popup_sign', ['m' => $this->agreement()]); }
    public function update_onRenewForm() { $this->requireAdmin(); return $this->makePartial('popup_renew', ['m' => $this->agreement()]); }
    public function update_onReopenForm() { $this->requireAdmin(); return $this->makePartial('popup_reopen', ['m' => $this->agreement()]); }
    public function update_onCancelForm() { return $this->makePartial('popup_cancel', ['m' => $this->agreement()]); }

    public function update_onRegenerate() { return $this->act(fn ($s, $a) => $s->regenerate($a), 'Texto regenerado.'); }
    public function update_onSubmit() { return $this->act(fn ($s, $a) => $s->submitForReview($a), 'Enviado a revisión.'); }
    public function update_onApprove() { return $this->act(fn ($s, $a) => $s->approve($a), 'Contrato aprobado.', true); }
    public function update_onSign() { return $this->act(fn ($s, $a) => $s->sign($a, post('signed_on') ?: null), 'Firma registrada.', true); }
    public function update_onReopen() { return $this->act(fn ($s, $a) => $s->reopen($a, (string) post('reason')), 'Contrato reabierto.', true); }
    public function update_onFinish() { return $this->act(fn ($s, $a) => $s->finish($a), 'Contrato finalizado.', true); }
    public function update_onCancel() { return $this->act(fn ($s, $a) => $s->cancel($a, (string) post('reason')), 'Contrato cancelado.'); }

    public function update_onRenew()
    {
        $amount = post('amount');

        return $this->act(fn ($s, $a) => $s->renew($a, (int) post('months'), $amount === null || $amount === '' ? null : (float) $amount, post('note')), 'Contrato renovado.', true);
    }
}
