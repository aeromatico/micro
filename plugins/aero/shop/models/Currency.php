<?php namespace Aero\Shop\Models;

use Model;

class Currency extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_currencies';

    public $fillable = ['code', 'name', 'symbol', 'decimal_places', 'is_active'];

    public $rules = [
        'code'   => 'required|min:3|max:5',
        'name'   => 'required|max:100',
        'symbol' => 'required|max:10',
    ];

    public $hasMany = [
        'tenant_currencies' => [TenantCurrency::class],
    ];

    public function format(float $amount): string
    {
        return $this->symbol . ' ' . number_format($amount, $this->decimal_places);
    }
}
