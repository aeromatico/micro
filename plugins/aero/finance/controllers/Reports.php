<?php namespace Aero\Finance\Controllers;

use Aero\Finance\Classes\AccountSeeder;
use Aero\Finance\Classes\CurrentTenant;
use Aero\Finance\Classes\Reports as ReportService;
use Aero\Finance\Models\Account;
use Backend\Classes\Controller;
use BackendMenu;
use Carbon\Carbon;

class Reports extends Controller
{
    public $requiredPermissions = ['aero.finance.use', 'aero.finance.superadmin'];

    public $pageTitle = 'Libro mayor y reportes';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Finance', 'finance', 'reports');
    }

    public function index(): void
    {
        try {
            $to = Carbon::parse(input('to', today()->toDateString()))->endOfDay();
            $from = Carbon::parse(input('from', today()->startOfMonth()->toDateString()))->startOfDay();
        } catch (\Throwable $e) {
            $to = today()->endOfDay();
            $from = today()->startOfMonth()->startOfDay();
        }
        if ($from->gt($to) || $from->diffInDays($to, true) > 800) {
            $from = $to->copy()->startOfMonth();
        }

        // Falla cerrado: sin tenant y sin ser superadmin, no hay reporte.
        $tenantId = CurrentTenant::isAdmin() ? null : CurrentTenant::id();
        if (!CurrentTenant::isAdmin() && !$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }
        if ($tenantId) {
            AccountSeeder::ensure($tenantId);
        }

        $r = new ReportService($tenantId);
        $accounts = Account::visible()->orderBy('code')->get();
        $accountId = (int) input('account', optional($accounts->firstWhere('system_key', 'cash'))->id ?? 0);

        $this->vars += [
            'from'       => $from,
            'to'         => $to,
            'monthly'    => $r->monthly($from->copy()->startOfYear()->min($from), $to),
            'categories' => $r->byCategory($from, $to),
            'accounts'   => $accounts,
            'accountId'  => $accountId,
            'ledger'     => $accountId ? $r->ledger($accountId, $from, $to) : null,
            'trial'      => $r->trialBalance($to),
        ];
    }
}
