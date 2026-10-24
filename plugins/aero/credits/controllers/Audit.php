<?php namespace Aero\Credits\Controllers;

use Aero\Credits\Classes\CreditUsage;
use Aero\Credits\Models\CreditAction;
use Aero\Credits\Models\CreditChargeFailure;
use Aero\Credits\Models\CreditTransaction;
use Aero\Credits\Models\CreditType;
use Backend\Classes\Controller;
use BackendMenu;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de cobros (superadmin): dónde se cobra, qué falta en el catálogo o
 * en el tarifario público y qué cobros fallaron sin que el tenant se enterara.
 */
class Audit extends Controller
{
    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'audit');
        $this->pageTitle = 'Auditoría de cobros';
    }

    public function index()
    {
        $days = in_array((int) input('days'), [7, 30, 90], true) ? (int) input('days') : 30;
        $since = now()->subDays($days);
        $types = CreditType::get()->keyBy('id');

        $charges = CreditTransaction::query()
            ->whereIn('kind', ['charge', 'refund'])->where('created_at', '>=', $since)
            ->select('action_code', 'source_plugin', 'credit_type_id', 'kind', DB::raw('COUNT(*) n'), DB::raw('SUM(delta) s'))
            ->groupBy('action_code', 'source_plugin', 'credit_type_id', 'kind')->get();

        $catalog = CreditAction::get()->keyBy('code');
        $byPlugin = $uncataloged = $notPublic = [];

        foreach ($charges as $r) {
            $sign = $r->kind === 'charge' ? 1 : -1;
            $type = $types[$r->credit_type_id] ?? null;
            $plugin = $r->source_plugin ?: (CreditUsage::pluginFor((string) $r->action_code) ?: '(sin plugin)');

            $row = &$byPlugin[$plugin][$type->label ?? '?'];
            $row['color'] = $type->color ?? '#999';
            $row['uses'] = ($row['uses'] ?? 0) + $sign * (int) $r->n;
            $row['amount'] = ($row['amount'] ?? 0) - (int) $r->s;
            unset($row);

            $d = CreditUsage::describe($r->action_code);
            $action = $catalog[$r->action_code] ?? null;

            if (!$action && $r->kind === 'charge') {
                $uncataloged[$r->action_code] = [
                    'code' => $r->action_code, 'plugin' => $plugin, 'uses' => ($uncataloged[$r->action_code]['uses'] ?? 0) + (int) $r->n,
                    'dynamic' => $d['key'] !== $r->action_code,
                ];
            }

            if ($r->kind === 'charge' && (!$action || !$action->is_public)) {
                $notPublic[$d['key']] = ['label' => $d['label'], 'plugin' => $plugin, 'uses' => ($notPublic[$d['key']]['uses'] ?? 0) + (int) $r->n];
            }
        }

        ksort($byPlugin);

        $this->vars['days'] = $days;
        $this->vars['byPlugin'] = $byPlugin;
        $this->vars['uncataloged'] = collect($uncataloged)->sortByDesc('uses')->values();
        $this->vars['notPublic'] = collect($notPublic)->sortByDesc('uses')->values();
        $this->vars['freeActions'] = CreditAction::where('is_active', true)->where('default_cost', 0)->orderBy('code')->pluck('code');
        $this->vars['noPlugin'] = CreditTransaction::where('kind', 'charge')->whereNull('source_plugin')->count();
        $this->vars['failures'] = CreditChargeFailure::where('created_at', '>=', $since)
            ->select('action_code', 'source_plugin', DB::raw('COUNT(*) n'), DB::raw('MAX(created_at) last_at'), DB::raw('MAX(reason) reason'))
            ->groupBy('action_code', 'source_plugin')->orderByDesc('n')->get();
    }
}
