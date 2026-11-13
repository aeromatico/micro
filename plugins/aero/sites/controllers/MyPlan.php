<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\PlanStatus;
use Aero\Sites\Classes\RenewalIssuer;
use Aero\Sites\Models\PlanRenewal;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * «Mi plan»: vigencia del plan del tenant, QR de renovación pendiente (con
 * monto e instrucciones) e historial de pagos. Solo lee/emite para el tenant
 * resuelto; sin tenant no muestra nada (falla cerrado).
 */
class MyPlan extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.sites.myplan'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'mi-plan');
    }

    public function index()
    {
        $this->pageTitle = 'Mi plan';
        $this->fillVars();
    }

    /** Emite el QR del ciclo si aún no existe («Renovar ahora»). */
    public function onRenew()
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            throw new \ApplicationException('No se encontró tu espacio.');
        }

        if (!RenewalIssuer::issue($tenant)) {
            throw new \ApplicationException('No se pudo generar el cobro ahora. Escribe a soporte y lo resolvemos.');
        }

        $this->fillVars();

        return ['#myplan-body' => $this->makePartial('body')];
    }

    /** Sondeo de la pantalla: refresca el cuerpo cuando el pago se confirma. */
    public function onRefresh()
    {
        $this->fillVars();

        return ['#myplan-body' => $this->makePartial('body')];
    }

    protected function fillVars(): void
    {
        $tenant = $this->getCurrentTenant();

        $this->vars['tenant'] = $tenant;
        $this->vars['status'] = $tenant ? PlanStatus::for($tenant) : null;
        $this->vars['phrase'] = $this->vars['status'] ? PlanStatus::phrase($this->vars['status']) : '';
        $this->vars['plan'] = $tenant?->plan;
        $this->vars['renewal'] = $tenant ? RenewalIssuer::current($tenant) : null;
        $this->vars['qr'] = $this->vars['renewal']?->qr_code_id
            ? \Aero\Pay\Models\QrCode::find($this->vars['renewal']->qr_code_id)
            : null;
        $this->vars['history'] = $tenant
            ? PlanRenewal::where('tenant_id', $tenant->id)->where('status', 'paid')->latest('paid_at')->limit(12)->get()
            : collect();
    }
}
