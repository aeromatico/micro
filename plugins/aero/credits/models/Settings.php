<?php namespace Aero\Credits\Models;

use Model;

/**
 * Ajustes globales del sistema de créditos. El valor por moneda vive en
 * CreditType (usd_value, price_bob); acá van las políticas de venta.
 * El bloqueo por saldo insuficiente ya no es opcional: la BD no admite
 * saldos negativos.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_credits_settings';

    public $settingsFields = 'fields.yaml';

    /** % de comisión que se cobra sobre las monedas que se intercambian. */
    public static function exchangeFeePercent(): float
    {
        return (float) self::get('exchange_fee_percent', 10);
    }

    /** Montos de recarga ofrecidos, en Bs. */
    public static function rechargeAmounts(): array
    {
        $raw = (string) self::get('recharge_amounts', '20,50,100,200,1000');
        $amounts = array_values(array_unique(array_filter(array_map(fn ($v) => (int) trim($v), explode(',', $raw)), fn ($v) => $v > 0)));
        sort($amounts);

        return $amounts ?: [20, 50, 100, 200, 1000];
    }

    /** Minutos que el tenant tiene para pagar el QR de una recarga. */
    public static function purchaseTtlMinutes(): int
    {
        return max(1, (int) self::get('purchase_ttl_minutes', 15));
    }

    /** Cuenta bancaria que recibe las recargas; sin elegir, usa la del alta pública de Aero.Sites. */
    public static function purchaseBankAccount(): ?\Aero\Pay\Models\BankAccount
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return null;
        }

        if ($id = self::get('purchase_bank_account_id')) {
            return \Aero\Pay\Models\BankAccount::active()->find($id);
        }

        return class_exists(\Aero\Sites\Models\Settings::class) ? \Aero\Sites\Models\Settings::getSignupBankAccount() : null;
    }

    public function getPurchaseBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return [];
        }

        return \Aero\Pay\Models\BankAccount::active()->pluck('label', 'id')->all();
    }
}
