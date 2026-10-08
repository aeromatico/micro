<?php namespace Aero\Finance\Controllers;

use Aero\Finance\Classes\AccountSeeder;
use Aero\Finance\Classes\CurrentTenant;
use Aero\Finance\Classes\PettyCashService;
use Aero\Finance\Classes\ScopesToTenant;
use Aero\Finance\Models\Account;
use Aero\Finance\Models\Movement;
use Aero\Finance\Models\PettyFund;
use Aero\Finance\Models\PettyOperation;
use Backend\Classes\Controller;
use BackendMenu;

class PettyCash extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.finance.use', 'aero.finance.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Finance', 'finance', 'pettycash');
    }

    public function formBeforeCreate($model): void
    {
        $model->tenant_id = CurrentTenant::id();
        if (!$model->tenant_id) {
            throw new \ApplicationException('No se pudo determinar el negocio de este libro.');
        }
    }

    public function index()
    {
        if (!\Aero\Finance\Models\FinanceSettings::isEnabled(CurrentTenant::id())) {
            \Flash::warning('Finanzas está desactivado: solo consulta. Actívelo en Configuración.');
        }
        $this->asExtension('ListController')->index();
    }

    /** Datos de la pantalla del fondo: saldo, cuentas a elegir e historial. */
    public function update($recordId = null, $context = null)
    {
        $this->asExtension('FormController')->update($recordId, $context);

        $fund = PettyFund::visible()->findOrFail((int) $recordId);
        $this->vars['fund'] = $fund;
        $this->vars['sources'] = Account::visible()->where('tenant_id', $fund->tenant_id)->where('type', 'asset')
            ->where('is_active', true)->where('id', '!=', $fund->account_id)->orderBy('code')->get();
        $this->vars['categories'] = Account::visible()->where('tenant_id', $fund->tenant_id)->where('type', 'expense')
            ->where('is_active', true)->orderBy('code')->get();
        $this->vars['operations'] = PettyOperation::visible()->where('fund_id', $fund->id)->orderByDesc('id')->limit(15)->get();
        $this->vars['expenses'] = Movement::visible()->where('cash_account_id', $fund->account_id)->orderByDesc('id')->limit(15)->get();
    }

    protected function fundFromPost(): PettyFund
    {
        return PettyFund::visible()->findOrFail((int) post('fund_id'));
    }

    protected function done(PettyFund $f, string $msg)
    {
        \Flash::success($msg);

        return \Backend::redirect('aero/finance/pettycash/update/' . $f->id);
    }

    public function onFundAdd()
    {
        $f = $this->fundFromPost();
        app(PettyCashService::class)->fund($f, post('amount'), (int) post('account_id'), post('date') ?: null, post('description'));

        return $this->done($f, 'Fondeo registrado.');
    }

    public function onFundReturn()
    {
        $f = $this->fundFromPost();
        app(PettyCashService::class)->giveBack($f, post('amount'), (int) post('account_id'), post('date') ?: null, post('description'));

        return $this->done($f, 'Devolución registrada.');
    }

    public function onFundCount()
    {
        $f = $this->fundFromPost();
        $op = app(PettyCashService::class)->count($f, post('counted'), post('date') ?: null, post('description'));

        return $this->done($f, abs($op->difference) < 0.01 ? 'Arqueo conforme: sin diferencia.'
            : sprintf('Arqueo registrado: %s de Bs %s.', $op->difference > 0 ? 'sobrante' : 'faltante', number_format(abs($op->difference), 2)));
    }

    public function onFundExpense()
    {
        $f = $this->fundFromPost();
        app(PettyCashService::class)->expense($f, [
            'amount' => post('amount'), 'date' => post('date') ?: today()->toDateString(),
            'category_account_id' => (int) post('category_account_id'), 'description' => post('description') ?: 'Gasto de caja chica',
            'counterparty' => post('counterparty'), 'document_no' => post('document_no'), 'tax_amount' => post('tax_amount') ?: 0,
        ]);

        return $this->done($f, 'Gasto registrado.');
    }

    public function onFundVoid()
    {
        $f = $this->fundFromPost();
        $op = PettyOperation::visible()->where('fund_id', $f->id)->findOrFail((int) post('operation_id'));
        app(PettyCashService::class)->void($op, 'Anulado desde el panel');

        return $this->done($f, 'Operación anulada.');
    }
}
