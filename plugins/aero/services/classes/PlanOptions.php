<?php namespace Aero\Services\Classes;

/** Opciones de dropdown reutilizadas por cada plan dentro del repetidor "Planes". */
class PlanOptions
{
    public static function typeOptions(): array
    {
        return [
            'one_time'  => 'Pago único',
            'recurring' => 'Suscripción / recurrente',
            'project'   => 'Proyecto',
            'hourly'    => 'Por hora',
        ];
    }

    public static function billingPeriodOptions(): array
    {
        return ['monthly' => 'Mensual', 'quarterly' => 'Trimestral', 'yearly' => 'Anual'];
    }

    public static function pricingModeOptions(): array
    {
        return ['money' => 'Solo dinero', 'credits' => 'Solo créditos', 'both' => 'Dinero o créditos (ambos)'];
    }

    public static function currencyOptions(): array
    {
        return ['BOB' => 'Bolivianos (BOB)', 'USD' => 'Dólares (USD)'];
    }

    /** [código => etiqueta] de los tipos de crédito activos; vacío si Aero.Credits no está instalado. */
    public static function creditTypeOptions(): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        return \Aero\Credits\Models\CreditType::active()->pluck('label', 'code')->all();
    }
}
