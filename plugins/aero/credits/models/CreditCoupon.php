<?php namespace Aero\Credits\Models;

use Model;

/**
 * Código público de promoción: cualquiera que lo tenga puede canjearlo en el
 * alta (o donde se exponga `Coupons::redeem()`) para obtener un plan gratis
 * por un periodo. Sin dueño ni tenant — lo crea el superadmin. `plan_id` es
 * una columna suelta (sin FK dura): Aero.Credits no depende de Aero.Sites.
 */
class CreditCoupon extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_credits_coupons';

    public $fillable = [
        'code', 'plan_id', 'period_unit', 'period_count',
        'max_redemptions', 'expires_at', 'is_active', 'note',
    ];

    public $rules = [
        'code'         => 'required|alpha_dash|max:40',
        'plan_id'      => 'required',
        'period_unit'  => 'required|in:monthly,annual',
        'period_count' => 'required|integer|min:1',
    ];

    protected $dates = ['expires_at'];

    protected $casts = [
        'is_active'       => 'boolean',
        'period_count'    => 'integer',
        'max_redemptions' => 'integer',
        'times_redeemed'  => 'integer',
    ];

    public function beforeValidate()
    {
        if ($this->code) {
            $this->code = strtoupper(str_replace(' ', '', $this->code));
        }
    }

    public function scopeRedeemable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_redemptions')->orWhereColumn('times_redeemed', '<', 'max_redemptions');
            });
    }

    public function getPlanIdOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Plan::class)
            ? \Aero\Sites\Models\Plan::active()->orderBy('sort_order')->pluck('name', 'id')->all()
            : [];
    }

    public function getPeriodUnitOptions(): array
    {
        return ['monthly' => 'Meses', 'annual' => 'Años'];
    }

    public function getPlanNameAttribute(): string
    {
        if (!$this->plan_id || !class_exists(\Aero\Sites\Models\Plan::class)) {
            return "Plan #{$this->plan_id}";
        }

        return \Aero\Sites\Models\Plan::find($this->plan_id)?->name ?? "Plan #{$this->plan_id}";
    }
}
