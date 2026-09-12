<?php namespace Aero\Credits\ReportWidgets;

use Aero\Credits\Models\CreditTransaction;
use Aero\Credits\Models\CreditType;
use Backend\Classes\ReportWidgetBase;

/**
 * Consumo global de créditos por color (todos los tenants) + top tenants del
 * mes. Solo tiene sentido para superadmins: se apoya en `requiredPermissions`
 * definido en el registro del widget para que October lo oculte a los demás.
 */
class GlobalCredits extends ReportWidgetBase
{
    protected $defaultAlias = 'aero_credits_global';

    public function render()
    {
        $this->vars['types'] = CreditType::active()->get()->map(function (CreditType $type) {
            $consumedMonth = abs((int) CreditTransaction::where('credit_type_id', $type->id)
                ->where('delta', '<', 0)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('delta'));

            return [
                'label'      => $type->label,
                'color'      => $type->color,
                'consumed'   => $consumedMonth,
                'usd_value'  => round($consumedMonth * (float) $type->usd_value, 2),
            ];
        })->all();

        $this->vars['topTenants'] = CreditTransaction::selectRaw('tenant_id, SUM(-delta) as total')
            ->where('delta', '<', 0)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->groupBy('tenant_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $name = "Tenant #{$row->tenant_id}";

                if (class_exists(\Aero\Sites\Models\Tenant::class)) {
                    $name = \Aero\Sites\Models\Tenant::where('id', $row->tenant_id)->value('name') ?: $name;
                }

                return ['name' => $name, 'total' => (int) $row->total];
            });

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'title' => [
                'title'   => 'Título',
                'default' => 'Consumo de créditos (global)',
                'type'    => 'string',
            ],
        ];
    }
}
