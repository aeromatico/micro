<?php namespace Aero\Shopify\Models;

use Aero\Shopify\Classes\CurrentTenant;
use Crypt;
use Model;
use Str;

/**
 * Una tienda Shopify conectada por un tenant. El token de Admin API y el
 * secreto de firma de webhooks se guardan cifrados (Crypt) en sus columnas;
 * el formulario usa los campos virtuales new_access_token / new_client_secret
 * (purgables) y nunca devuelve el valor guardado.
 */
class Store extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shopify_stores';

    public $fillable = ['tenant_id', 'name', 'shop_domain', 'bank_account_id', 'gateway_names', 'is_active'];

    public $rules = [
        'name'        => 'required|string|max:255',
        'shop_domain' => 'required|regex:/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i|unique:aero_shopify_stores,shop_domain',
    ];

    public $customMessages = [
        'shop_domain.regex' => 'El dominio debe ser del estilo mi-tienda.myshopify.com.',
    ];

    public $purgeable = ['new_access_token', 'new_client_secret'];

    public $hidden = ['access_token', 'client_secret'];

    public $hasMany = [
        'orders' => [Order::class, 'key' => 'store_id'],
    ];

    public function beforeCreate()
    {
        $this->uuid = $this->uuid ?: (string) Str::uuid();
        $this->shop_domain = strtolower(trim((string) $this->shop_domain));
    }

    public function beforeSave()
    {
        $this->shop_domain = strtolower(trim((string) $this->shop_domain));
    }

    public function setSecrets(?string $accessToken, ?string $clientSecret): void
    {
        if ($accessToken !== null && trim($accessToken) !== '') {
            $this->access_token = Crypt::encryptString(trim($accessToken));
        }
        if ($clientSecret !== null && trim($clientSecret) !== '') {
            $this->client_secret = Crypt::encryptString(trim($clientSecret));
        }
    }

    public function accessToken(): ?string
    {
        return $this->decrypt($this->access_token);
    }

    public function clientSecret(): ?string
    {
        return $this->decrypt($this->client_secret);
    }

    protected function decrypt(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** ¿El método de pago del pedido es el nuestro? Compara sin mayúsculas. */
    public function matchesGateway(array $gatewayNames): bool
    {
        $mine = array_filter(array_map(fn ($g) => mb_strtolower(trim($g)), explode(',', (string) $this->gateway_names)));
        foreach ($gatewayNames as $name) {
            if (in_array(mb_strtolower(trim((string) $name)), $mine, true)) {
                return true;
            }
        }

        return false;
    }

    public function webhookUrl(): string
    {
        return url('api/v1/shopify/webhooks/' . $this->uuid);
    }

    public function lookupUrl(): string
    {
        return url('shopify/pagar/' . $this->uuid);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTenantIdOptions(): array
    {
        return \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Solo cuentas con API de banco del tenant dueño de la tienda (no QR estático). */
    public function getBankAccountIdOptions(): array
    {
        if (!class_exists(\Aero\Pay\Models\BankAccount::class)) {
            return [];
        }

        $tenantId = $this->tenant_id ?: CurrentTenant::id();
        if (!$tenantId) {
            return [];
        }

        $manager = app(\Aero\Pay\Classes\PaymentDriverManager::class);

        return \Aero\Pay\Models\BankAccount::active()->forTenant($tenantId)->get()
            ->reject(fn ($a) => $manager->flowFor($a->bank_code) === 'qr_static')
            ->pluck('label', 'id')->all();
    }

    /** La cuenta bancaria elegida debe ser del mismo tenant que la tienda. */
    public function bankAccount(): ?\Aero\Pay\Models\BankAccount
    {
        if (!$this->bank_account_id || !class_exists(\Aero\Pay\Models\BankAccount::class) || !\Schema::hasTable('aero_pay_bank_accounts')) {
            return null;
        }

        return \Aero\Pay\Models\BankAccount::active()
            ->forTenant((int) $this->tenant_id)
            ->find($this->bank_account_id);
    }
}
