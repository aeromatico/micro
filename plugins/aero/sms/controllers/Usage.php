<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Classes\Billing;
use Aero\Sms\Classes\CurrentTenant;
use Aero\Sms\Models\Message;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Consumo. El superadmin lo ve agregado por tenant y key (lo que hay que saber
 * para cobrar y auditar); el tenant ve el suyo por día y por key, con su saldo.
 */
class Usage extends Controller
{
    public $requiredPermissions = ['aero.sms.use', 'aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'usage');
        $this->pageTitle = CurrentTenant::isAdmin() ? 'Consumo por tenant' : 'Mi consumo';
    }

    protected const SUMS = "COUNT(*) as messages, SUM(segments) as segments,
        SUM(status = 'delivered') as delivered, SUM(status IN ('failed','undelivered')) as failed,
        SUM(CASE WHEN refunded_at IS NULL THEN credits_charged ELSE 0 END) as credits";

    public function index(): void
    {
        $days = min(365, max(1, (int) input('days', 30)));
        $since = now()->subDays($days)->startOfDay();
        $isAdmin = CurrentTenant::isAdmin();

        $this->vars['days'] = $days;
        $this->vars['isAdmin'] = $isAdmin;

        if ($isAdmin) {
            $this->vars['rows'] = Message::where('created_at', '>=', $since)
                ->selectRaw('tenant_id, api_key_id, MAX(consumer) as consumer, ' . self::SUMS)
                ->groupBy('tenant_id', 'api_key_id')
                ->orderByDesc('messages')
                ->get()
                ->each(fn ($r) => $r->tenant_label = $r->tenant_id ? (CurrentTenant::name($r->tenant_id) ?? "Tenant #{$r->tenant_id}") : 'Plataforma');

            return;
        }

        $tenantId = CurrentTenant::id();
        $base = fn () => Message::where('created_at', '>=', $since)->where('tenant_id', $tenantId ?: 0);

        $this->vars['byDay'] = $base()->selectRaw('DATE(created_at) as day, ' . self::SUMS)->groupBy('day')->orderByDesc('day')->get();
        $this->vars['byKey'] = $base()->selectRaw('COALESCE(consumer, "—") as consumer, ' . self::SUMS)->groupBy('consumer')->orderByDesc('messages')->get();
        $this->vars['balance'] = $tenantId && Billing::enabled()
            ? \Aero\Credits\Classes\Credits::balance($tenantId, \Aero\Credits\Classes\Credits::cost(Billing::ACTION)['type']->code ?? 'azul')
            : null;
        $this->vars['perSegment'] = Billing::quote(1);
    }
}
