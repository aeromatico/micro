<?php namespace Aero\Credits\ReportWidgets;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Classes\Money;
use Aero\Credits\Models\CreditType;
use Backend\Classes\ReportWidgetBase;

/**
 * "Mis créditos": saldo del tenant actual por color. Visible para
 * tenant_admin y superadmin (editando un site). Si no hay tenant resoluble
 * (superadmin sin site elegido), no se muestra nada.
 */
class MyCredits extends ReportWidgetBase
{
    protected $defaultAlias = 'aero_credits_my_credits';

    public function render()
    {
        $tenantId = Credits::resolveCurrentTenantId();

        $this->vars['tenantId'] = $tenantId;
        $this->vars['types'] = [];

        if ($tenantId) {
            $this->vars['types'] = CreditType::active()->get()->map(function (CreditType $type) use ($tenantId) {
                $balance = Credits::balance($tenantId, $type->code);

                return [
                    'label'     => preg_replace('/^Monedas\b/u', 'Créditos', (string) $type->label),
                    'color'     => $type->color,
                    'balance'   => $balance,
                    'bob_value' => Money::format(Money::units($balance * (float) $type->price_bob)),
                ];
            })->all();
        }

        return $this->makePartial('widget');
    }

    public function defineProperties()
    {
        return [
            'title' => [
                'title'   => 'Título',
                'default' => 'Mis créditos',
                'type'    => 'string',
            ],
        ];
    }
}
