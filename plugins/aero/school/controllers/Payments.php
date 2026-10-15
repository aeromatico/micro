<?php namespace Aero\School\Controllers;

use Aero\School\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

/** Historial de pagos y recibos. Los pagos se registran desde «Cobrar»; aquí se consultan, imprimen y anulan. */
class Payments extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'payments');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeToTenant($query);
        $query->with('student');
    }

    protected function findPayment($id): \Aero\School\Models\Payment
    {
        return $this->scopeToTenant(\Aero\School\Models\Payment::query())->with('student', 'guardian', 'items.charge')->findOrFail($id);
    }

    public function receipt($id = null)
    {
        $this->pageTitle = 'Recibo';
        $this->vars['payment'] = $this->findPayment($id);
        $this->vars['settings'] = \Aero\School\Models\Settings::forTenant($this->vars['payment']->tenant_id);
    }

    public function receipt_onVoid($id = null)
    {
        $payment = $this->findPayment($id);
        app(\Aero\School\Classes\Billing::class)->voidPayment($payment, trim((string) post('reason')) ?: null);
        \Flash::success('Pago anulado: las cuotas volvieron a quedar pendientes.');

        return \Redirect::refresh();
    }
}
