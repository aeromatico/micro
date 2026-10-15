<?php namespace Aero\School\Controllers;

use Aero\School\Classes\Billing;
use Aero\School\Classes\TenantScreen;
use Aero\School\Models\Charge;
use Aero\School\Models\Course;
use Aero\School\Models\FeePlan;
use Aero\School\Models\Year;
use Backend\Classes\Controller;
use BackendMenu;

/** Precios por nivel: matrícula y pensión de Inicial / Primaria / Secundaria en una sola pantalla. */
class Prices extends Controller
{
    use TenantScreen;

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'prices');
        $this->pageTitle = 'Pensiones y matrícula';
    }

    public function index()
    {
        $tenantId = $this->tenantId();
        $year = Year::currentFor($tenantId);
        $plans = $year
            ? FeePlan::where('tenant_id', $tenantId)->where('year_id', $year->id)->whereNull('course_id')->whereNotNull('level')->where('is_active', true)->get()
            : collect();

        $prices = [];
        foreach (array_keys(Course::LEVELS) as $level) {
            $prices[$level] = [
                'matricula' => $plans->first(fn ($p) => $p->level === $level && $p->kind === 'matricula')?->amount,
                'pension'   => $plans->first(fn ($p) => $p->level === $level && $p->kind === 'pension')?->amount,
            ];
        }
        $pension = $plans->firstWhere('kind', 'pension');

        $this->vars += [
            'year'         => $year,
            'prices'       => $prices,
            'installments' => $pension->installments ?? 10,
            'firstMonth'   => $pension->first_month ?? 2,
            'dueDay'       => $pension->due_day ?? 10,
            'months'       => (new FeePlan)->getFirstMonthOptions(),
            'students'     => \Aero\School\Models\Student::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'exceptions'   => FeePlan::where('tenant_id', $tenantId)->where('is_active', true)->where(fn ($q) => $q->whereNotNull('course_id')->orWhereNull('level'))->count(),
        ];
    }

    public function onSave()
    {
        $tenantId = $this->tenantId();
        $year = Year::currentFor($tenantId);
        if (!$year) {
            throw new \ApplicationException('Primero cree la gestión (año escolar) en «Gestiones».');
        }

        $installments = min(12, max(1, (int) post('installments', 10)));
        $firstMonth = min(12, max(1, (int) post('first_month', 2)));
        $dueDay = min(28, max(1, (int) post('due_day', 10)));
        $prices = (array) post('prices', []);
        if (!array_filter($prices, 'is_array')) {
            throw new \ApplicationException('No llegaron los precios; recargue la pantalla e intente de nuevo.');
        }
        $created = 0;
        $saved = 0;

        foreach (Course::LEVELS as $level => $label) {
            foreach (['matricula' => ['Matrícula', 1, 2], 'pension' => ['Pensión mensual', $installments, $firstMonth]] as $kind => [$name, $n, $month]) {
                $raw = str_replace(',', '.', trim((string) ($prices[$level][$kind] ?? '')));
                $plan = FeePlan::where('tenant_id', $tenantId)->where('year_id', $year->id)->where('level', $level)
                    ->whereNull('course_id')->where('kind', $kind)->first();

                if ($raw === '' || !is_numeric($raw) || (float) $raw <= 0) {
                    if ($plan && $plan->is_active) {
                        $plan->update(['is_active' => false]);
                    }
                    continue;
                }

                $amount = round((float) $raw, 2);
                $plan = $plan ?: new FeePlan(['tenant_id' => $tenantId, 'year_id' => $year->id, 'level' => $level, 'kind' => $kind]);
                $plan->fill([
                    'name' => "{$name} — {$label}", 'amount' => $amount, 'installments' => $n,
                    'first_month' => $month, 'due_day' => $dueDay, 'is_active' => true,
                ]);
                $plan->save();
                $saved++;

                // Si había un concepto «para todo el colegio» del mismo tipo, este por nivel lo reemplaza.
                FeePlan::where('tenant_id', $tenantId)->where('year_id', $year->id)->where('kind', $kind)
                    ->whereNull('level')->whereNull('course_id')->where('is_active', true)->update(['is_active' => false]);

                // Un cambio de precio alcanza a las cuotas del nivel que aún no tienen pago.
                Charge::where('tenant_id', $tenantId)->where('year_id', $year->id)->where('kind', $kind)
                    ->where('status', 'pending')->where('paid_amount', '<=', 0)
                    ->whereHas('student.course', fn ($q) => $q->where('level', $level))
                    ->get()->each(function (Charge $c) use ($amount) {
                        $pct = (float) ($c->student?->discount_pct ?? 0);
                        $c->update(['amount' => $amount, 'discount' => $pct > 0 ? round($amount * $pct / 100, 2) : 0]);
                    });

                $created += app(Billing::class)->generate($plan);
            }
        }

        \Flash::success($saved
            ? "Precios guardados. Se generaron {$created} cuotas nuevas para los estudiantes inscritos."
            : 'No ingresó ningún precio.');

        return \Redirect::refresh();
    }
}
