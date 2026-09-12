<?php namespace Aero\Credits\Models;

use Model;

/**
 * Catálogo editable de acciones facturables (ej. "aifields.complete",
 * "hello.voice_call"). El superadmin ajusta `default_cost`/`credit_type_id`
 * sin deploy — ver Aero\Credits\Classes\CreditActionCatalog para los
 * valores sembrados de fábrica.
 */
class CreditAction extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_credits_actions';

    public $fillable = [
        'code', 'label', 'plugin', 'credit_type_id', 'default_cost', 'is_active',
    ];

    public $rules = [
        'code'           => 'required|unique:aero_credits_actions,code',
        'label'          => 'required',
        'credit_type_id' => 'required',
        'default_cost'   => 'required|integer|min:0',
    ];

    public $attributes = [
        'default_cost' => 1,
        'is_active'    => true,
    ];

    protected $casts = [
        'default_cost' => 'integer',
        'is_active'    => 'boolean',
    ];

    public $belongsTo = [
        'creditType' => \Aero\Credits\Models\CreditType::class,
    ];

    public function getCreditTypeIdOptions(): array
    {
        return CreditType::active()->pluck('label', 'id')->all();
    }

    public static function findActive(string $code): ?self
    {
        return static::where('code', $code)->where('is_active', true)->first();
    }
}
