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
        'code', 'label', 'color', 'usd_value', 'low_balance_threshold', 'is_active', 'sort_order',
    ];

    public $rules = [
        'code'  => 'required|alpha_dash|unique:aero_credits_types,code',
        'label' => 'required',
    ];

    public $attributes = [
        'color'                 => '#3b82f6',
        'usd_value'             => 0.0100,
        'low_balance_threshold' => 50,
        'is_active'             => true,
        'sort_order'            => 0,
    ];

    protected $casts = [
        'usd_value'             => 'float',
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
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
