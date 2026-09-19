<?php namespace Aero\Sms\Models;

use Aero\Sms\Classes\PhoneNumber;
use Model;

/** Lista de supresión global: a estos números no se envía nada, nunca. */
class OptOut extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sms_optouts';

    public $fillable = ['phone', 'source', 'reason'];

    public $rules = [
        'phone' => 'required|unique:aero_sms_optouts,phone',
    ];

    public function beforeValidate(): void
    {
        $this->phone = PhoneNumber::normalize($this->phone) ?? $this->phone;
    }

    public static function blocks(string $e164): bool
    {
        return static::where('phone', $e164)->exists();
    }
}
