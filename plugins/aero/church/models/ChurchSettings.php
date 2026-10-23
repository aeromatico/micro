<?php namespace Aero\Church\Models;

use Model;

/** Configuración de aportes de la iglesia (una fila por tenant). */
class ChurchSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_settings';

    public $attributes = ['currency' => 'BOB', 'receipt_prefix' => 'R'];

    public $fillable = [
        'tenant_id', 'bank_account_id', 'currency', 'receipt_prefix', 'welcome_enabled', 'prayer_enabled', 'welcome_text',
        'birthday_enabled', 'birthday_template', 'notify_event_changes', 'site_enabled', 'portal_enabled', 'live_url', 'live_title', 'live_schedule',
    ];

    public $nullable = ['bank_account_id'];

    public $rules = [
        'currency'       => 'required|in:BOB,USD',
        'receipt_prefix' => 'required|string|max:10',
    ];

    /** Enlace público de la iglesia (su dominio principal) para QR y para compartir. */
    public function publicUrl(string $path): ?string
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class) || !$this->tenant_id) {
            return null;
        }
        $domain = \Aero\Sites\Models\Tenant::find($this->tenant_id)?->primary_domain;

        return $domain ? 'https://' . $domain . $path : null;
    }

    public static function forTenant(?int $tenantId): self
    {
        return self::firstOrCreate(['tenant_id' => $tenantId]);
    }

    public function getCurrencyOptions(): array
    {
        return ['BOB' => 'Bolivianos (BOB)', 'USD' => 'Dólares (USD)'];
    }

    /** Cuentas de Pay del propio tenant; sin Pay, sin opciones. */
    public function getBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class) || !$this->tenant_id) {
            return [];
        }

        return \Aero\Pay\Models\BankAccount::active()->forTenant((int) $this->tenant_id)->get()
            ->mapWithKeys(fn ($b) => [$b->id => $b->label ?: ('Cuenta #' . $b->id)])->all();
    }

    public function beforeValidate(): void
    {
        if ($this->live_url && !\Aero\Church\Classes\Embed::isHttps($this->live_url)) {
            throw new \ApplicationException('El enlace de la transmisión debe empezar con https://');
        }

        // La cuenta elegida debe ser del mismo tenant (id manipulado en el POST).
        if ($this->bank_account_id && !array_key_exists($this->bank_account_id, $this->getBankAccountIdOptions())) {
            throw new \ApplicationException('La cuenta bancaria no es válida para esta iglesia.');
        }
    }
}
