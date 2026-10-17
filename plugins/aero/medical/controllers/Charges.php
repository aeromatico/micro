<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\ChargeService;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\Charge;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Patient;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Cobros de la atención. Lo opera recepción (caja) o personal clínico; no muestra contenido clínico: solo
 * paciente, concepto, monto y estado. Efectivo/transferencia quedan cobrados; el QR se emite con Aero.Pay.
 */
class Charges extends Controller
{
    use ScopesToTenant;

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.reception', 'aero.medical.use', 'aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'charges');
    }

    public function index(): void
    {
        if (request()->ajax()) {
            return;
        }
        $this->pageTitle = 'Cobros';

        $status = (string) get('status');
        $q = trim((string) get('q'));
        $tenantId = (int) \Aero\Medical\Classes\CurrentTenant::id();

        $charges = $this->scopeToTenant(Charge::with('patient'))
            ->when(isset(Charge::STATUSES[$status]), fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->whereHas('patient', fn ($p) => $p->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%')))
            ->orderByDesc('id')->limit(80)->get();

        // Prellenado desde una nota firmada (monto y concepto de su reserva, si la tiene).
        $pre = ['patient_id' => '', 'concept' => '', 'amount' => '', 'currency' => 'BOB', 'consultation_id' => ''];
        if ($cid = (int) get('consultation_id')) {
            $c = $this->scopeToTenant(Consultation::with('patient')->whereKey($cid))->first();
            if ($c) {
                $pre = ['patient_id' => $c->patient_id, 'consultation_id' => $c->id, 'concept' => $c->service_name ?: 'Atención', 'amount' => '', 'currency' => 'BOB'];
                if ($c->booking_id && \Aero\Medical\Classes\ConsultationService::officeAvailable() && ($b = \Aero\Office\Models\Booking::where('tenant_id', $c->tenant_id)->find($c->booking_id))) {
                    $pre['amount'] = (float) $b->price > 0 ? number_format((float) $b->price, 2, '.', '') : '';
                    $pre['currency'] = $b->currency ?: 'BOB';
                }
            }
        }

        $this->vars += [
            'charges' => $charges, 'status' => $status, 'q' => $q, 'pre' => $pre,
            'patients' => $this->scopeToTenant(Patient::where('is_active', true))->orderBy('name')->limit(500)->get(['id', 'name']),
            'accounts' => ChargeService::bankAccounts($tenantId),
            'payOk' => ChargeService::payAvailable(),
        ];
    }

    public function update($id = null): void
    {
        if (request()->ajax()) {
            return;
        }
        $this->pageTitle = 'Cobro';
        $c = $this->chargeOrFail($id);
        $this->vars['c'] = $c;
        $this->vars['qr'] = $c->qr_code_id && ChargeService::payAvailable() ? \Aero\Pay\Models\QrCode::find($c->qr_code_id) : null;
    }

    public function index_onCreate()
    {
        $p = $this->scopeToTenant(Patient::whereKey((int) post('patient_id')))->first();
        if (!$p) {
            throw new \ApplicationException('Elija un paciente.');
        }

        $c = $this->guard(fn () => app(ChargeService::class)->create($p, [
            'concept' => post('concept'), 'amount' => post('amount'), 'currency' => post('currency'), 'method' => post('method'),
            'consultation_id' => post('consultation_id'), 'bank_account_id' => post('bank_account_id'),
        ], BackendAuth::getUser()));

        return \Backend::redirect('aero/medical/charges/update/' . $c->id);
    }

    public function update_onMarkPaid($id = null)
    {
        $c = $this->chargeOrFail($id);
        $this->guard(fn () => app(ChargeService::class)->markPaid($c));
        \Flash::success('Cobro marcado como cobrado.');

        return \Backend::redirect('aero/medical/charges/update/' . $c->id);
    }

    public function update_onCancel($id = null)
    {
        $c = $this->chargeOrFail($id);
        $this->guard(fn () => app(ChargeService::class)->cancel($c, post('reason')));
        \Flash::success('Cobro anulado.');

        return \Backend::redirect('aero/medical/charges/update/' . $c->id);
    }

    protected function chargeOrFail($id): Charge
    {
        return $this->scopeToTenant(Charge::with('patient')->whereKey((int) $id))->firstOrFail();
    }

    protected function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
    }
}
