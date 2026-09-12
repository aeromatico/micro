<?php namespace Aero\Credits\Models;

use Model;

/**
 * Saldo de un tenant en un color de crédito específico. `balance` es un
 * caché de lectura rápida — la verdad real es la suma de deltas en
 * CreditTransaction; ver Aero\Credits\Classes\Credits::reconcile().
 *
 * `tenant_id` es una columna suelta (sin FK dura): Aero.Credits no depende de
 * Aero.Sites. Si Sites está instalado, agrega la relación `tenant` — mismo
 * patrón que usa Aero.Sites con Aero.Hello/Aero.Api.
 */
class CreditAccount extends Model
{
    public $table = 'aero_credits_accounts';

    public $fillable = ['tenant_id', 'credit_type_id', 'balance'];

    protected $casts = [
        'balance' => 'integer',
    ];

    public $belongsTo = [
        'creditType' => \Aero\Credits\Models\CreditType::class,
    ];

    public function getTenantNameAttribute(): string
    {
        if (class_exists(\Aero\Sites\Models\Tenant::class)) {
            $name = \Aero\Sites\Models\Tenant::where('id', $this->tenant_id)->value('name');

            if ($name) {
                return $name;
            }
        }

        return "Tenant #{$this->tenant_id}";
    }

    public static function forTenant(int $tenantId, int $creditTypeId): self
    {
        return static::firstOrCreate([
            'tenant_id'      => $tenantId,
            'credit_type_id' => $creditTypeId,
        ], ['balance' => 0]);
    }
}
