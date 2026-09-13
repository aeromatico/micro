<?php namespace Aero\Sites\Models;

use Model;

/**
 * Configuración a nivel de plataforma para Aero.Sites — actualmente solo el
 * banco de imágenes usado por el generador con IA. Separado de
 * Aero\AiFields\Models\Settings (que es genérico de proveedor LLM).
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_sites_settings';

    public $settingsFields = 'fields.yaml';

    public static function getUnsplashAccessKey(): ?string
    {
        return self::get('unsplash_access_key');
    }

    public static function isImageSourcingConfigured(): bool
    {
        return !empty(self::getUnsplashAccessKey());
    }

    public static function getImageCacheTtlDays(): int
    {
        return (int) self::get('image_cache_ttl_days', 30);
    }

    // -------------------------------------------------------------------------
    // Alta pública de tenants (/alta) — cobro del plan vía aero/qrbo
    // -------------------------------------------------------------------------

    public static function getSignupPlanPrice(string $plan): float
    {
        $default = $plan === 'pro' ? 99 : 49;
        return (float) self::get("signup_price_{$plan}", $default);
    }

    public static function getSignupBankAccountId(): ?int
    {
        $id = self::get('signup_bank_account_id');
        return $id ? (int) $id : null;
    }

    public static function getSignupBankAccount(): ?\Aero\Qrbo\Models\BankAccount
    {
        if (!class_exists(\Aero\Qrbo\Models\BankAccount::class)) {
            return null;
        }

        $id = static::getSignupBankAccountId();
        return $id ? \Aero\Qrbo\Models\BankAccount::active()->find($id) : null;
    }

    public static function getSignupPendingTtlHours(): int
    {
        return (int) self::get('signup_pending_ttl_hours', 2);
    }

    public function getSignupBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Qrbo\Models\BankAccount::class)) {
            return [];
        }

        return \Aero\Qrbo\Models\BankAccount::active()
            ->get()
            ->mapWithKeys(fn ($account) => [$account->id => "{$account->label} (tenant #{$account->tenant_id})"])
            ->toArray();
    }
}
