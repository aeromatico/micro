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
    // Alta pública de tenants (/alta) — cobro del plan vía aero/pay
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

    public static function getSignupBankAccount(): ?\Aero\Pay\Models\BankAccount
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return null;
        }

        $id = static::getSignupBankAccountId();
        return $id ? \Aero\Pay\Models\BankAccount::active()->find($id) : null;
    }

    /**
     * Minutos que tiene el visitante para pagar el QR de alta antes de que
     * se anule y se libere el handle reservado — ver
     * Console\ReleaseExpiredSignups (corre cada minuto) y el temporizador
     * de signup.js. Corto a propósito: es un pago inmediato con la app del
     * banco ya en la mano, no una reserva de horas.
     */
    public static function getSignupPaymentTtlMinutes(): int
    {
        return (int) self::get('signup_payment_ttl_minutes', 3);
    }

    /**
     * Cargo fijo en Bs por registrar un dominio propio en el alta (solo
     * ofrecido en el plan Pro) — no es el costo real del dominio en el
     * registrador (eso varía por extensión, ver clouds.com.bo/api/v1/domains),
     * es lo que cobramos nosotros por gestionarlo el primer año.
     */
    public static function getDomainRegistrationPrice(): float
    {
        return (float) self::get('signup_domain_price', 99);
    }

    /**
     * % que se suma al costo mayorista de renovación (en USD, ver
     * clouds.com.bo/api/v1/domains) antes de convertirlo a Bs y mostrárselo
     * al cliente — la registración es el cargo fijo de arriba, pero la
     * renovación del año 2 en adelante sí depende de la extensión elegida.
     */
    public static function getDomainRenewalMarkupPercent(): float
    {
        return (float) self::get('signup_domain_renewal_markup_percent', 30);
    }

    /**
     * Tasa USD -> BOB para mostrar el precio de renovación en bolivianos.
     * Reusa el servicio ya cacheado de aero/api (misma tasa que expone
     * api/v1/currencies) en vez de una llamada HTTP propia — evita duplicar
     * la key de exchangerate-api.com o depender de una API key nuestra.
     * Null si aero/api no está instalado o la tasa no está disponible
     * todavía (sin refrescar nunca) — el front oculta el precio de
     * renovación en ese caso en vez de mostrar un número inventado.
     */
    public static function getUsdToBobRate(): ?float
    {
        if (!class_exists(\Aero\Api\Classes\Currency\CurrencyRateService::class)) {
            return null;
        }

        try {
            return app(\Aero\Api\Classes\Currency\CurrencyRateService::class)->latest('USD')->rate('BOB');
        } catch (\Throwable) {
            return null;
        }
    }

    public function getSignupBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return [];
        }

        return \Aero\Pay\Models\BankAccount::active()
            ->get()
            ->mapWithKeys(fn ($account) => [$account->id => "{$account->label} (tenant #{$account->tenant_id})"])
            ->toArray();
    }
}
