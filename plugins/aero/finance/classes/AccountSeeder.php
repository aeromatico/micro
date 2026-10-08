<?php namespace Aero\Finance\Classes;

use Aero\Finance\Models\Account;

/**
 * Plan de cuentas boliviano simplificado (1 Activo, 2 Pasivo, 3 Patrimonio,
 * 4 Ingresos, 5 Egresos). Se siembra por tenant la primera vez que se usa.
 * `system_key` marca las cuentas que usan las integraciones.
 */
class AccountSeeder
{
    /** [código, nombre, tipo, system_key] */
    public const CHART = [
        ['1.1.01', 'Caja', 'asset', 'cash'],
        ['1.1.02', 'Bancos', 'asset', 'bank'],
        ['1.1.03', 'Cuentas por cobrar', 'asset', 'receivable'],
        ['1.1.05', 'IVA crédito fiscal', 'asset', 'vat_credit'],
        ['1.2.01', 'Inventarios', 'asset', null],
        ['1.3.01', 'Activo fijo', 'asset', null],
        ['2.1.01', 'IVA débito fiscal', 'liability', 'vat_debit'],
        ['2.1.02', 'Cuentas por pagar', 'liability', 'payable'],
        ['2.1.03', 'Impuesto a las transacciones (IT) por pagar', 'liability', null],
        ['3.1.01', 'Capital', 'equity', null],
        ['3.2.01', 'Resultados acumulados', 'equity', null],
        ['4.1.01', 'Ventas', 'income', 'sales'],
        ['4.1.02', 'Membresías y servicios', 'income', 'memberships'],
        ['4.1.03', 'Suscripciones de planes (portal)', 'income', 'plan_subscriptions'],
        ['4.1.04', 'Recargas de créditos (portal)', 'income', 'credit_sales'],
        ['4.9.99', 'Otros ingresos', 'income', 'other_income'],
        ['5.1.01', 'Compras y costo de ventas', 'expense', 'purchases'],
        ['5.2.01', 'Sueldos y cargas sociales', 'expense', null],
        ['5.2.02', 'Alquileres', 'expense', null],
        ['5.2.03', 'Servicios básicos', 'expense', null],
        ['5.2.04', 'Publicidad y marketing', 'expense', null],
        ['5.2.05', 'Impuestos y patentes', 'expense', null],
        ['5.2.06', 'Comisiones bancarias', 'expense', null],
        ['5.2.99', 'Otros gastos', 'expense', 'other_expense'],
    ];

    public static function ensure(int $tenantId): void
    {
        if (Account::where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach (self::CHART as [$code, $name, $type, $key]) {
            Account::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'system_key' => $key, 'is_active' => true]
            );
        }
    }

    /** Cuenta del sistema del tenant (la siembra si hace falta). */
    public static function system(int $tenantId, string $key): Account
    {
        self::ensure($tenantId);

        $found = Account::where('tenant_id', $tenantId)->where('system_key', $key)->first();
        if ($found) {
            return $found;
        }

        // Cuenta del plan añadida después de sembrar este tenant.
        foreach (self::CHART as [$code, $name, $type, $k]) {
            if ($k === $key) {
                return Account::firstOrCreate(
                    ['tenant_id' => $tenantId, 'code' => $code],
                    ['name' => $name, 'type' => $type, 'system_key' => $key, 'is_active' => true]
                );
            }
        }

        throw new FinanceException("Cuenta de sistema desconocida: {$key}");
    }
}
