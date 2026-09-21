<?php namespace Aero\Credits\Models;

use Model;

/**
 * Una "moneda" de crédito (ej. azul/rojo): define cuánto vale en USD y desde
 * qué saldo empezar a alertar. Las acciones facturables (CreditAction) y las
 * cuentas por tenant (CreditAccount) siempre cuelgan de un tipo — nunca hay
 * un crédito "sin color".
 */
class CreditType extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_credits_types';

    public $fillable = [
        'code', 'label', 'color', 'usd_value', 'price_bob', 'is_exchangeable', 'is_money', 'low_balance_threshold', 'is_active', 'sort_order',
    ];

    public $rules = [
        'code'  => 'required|alpha_dash|unique:aero_credits_types,code',
        'label' => 'required',
    ];

    public $attributes = [
        'color'                 => '#3b82f6',
        'usd_value'             => 0.0100,
        'price_bob'             => 0,
        'is_exchangeable'       => true,
        'low_balance_threshold' => 50,
        'is_active'             => true,
        'sort_order'            => 0,
    ];

    protected $casts = [
        'usd_value'             => 'float',
        'price_bob'             => 'float',
        'is_exchangeable'       => 'boolean',
        'is_money'              => 'boolean',
        'low_balance_threshold' => 'integer',
        'is_active'             => 'boolean',
        'sort_order'            => 'integer',
    ];

    public $hasMany = [
        'accounts' => [\Aero\Credits\Models\CreditAccount::class],
        'actions'  => [\Aero\Credits\Models\CreditAction::class],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_money', false)->orderBy('id');
    }

    /** La "moneda" que representa el dinero (Bs) de la billetera; no es una moneda de consumo. */
    public static function money(): ?self
    {
        return static::where('is_money', true)->first();
    }

    /** Precio por unidad en unidades de dinero (diezmilésimas de Bs). */
    public function priceUnits(): int
    {
        return \Aero\Credits\Classes\Money::units($this->price_bob);
    }

    public function beforeDelete()
    {
        if ($this->is_money) {
            throw new \ApplicationException('La moneda de dinero (Bs) es parte del sistema y no se puede eliminar.');
        }
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
