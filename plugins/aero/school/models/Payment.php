<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

class Payment extends Model
{
    use TenantOwned;

    public $table = 'aero_school_payments';

    public $fillable = ['tenant_id', 'student_id', 'guardian_id', 'receipt_no', 'amount', 'method', 'reference', 'paid_at', 'received_by', 'notes', 'voided_at'];

    public $dates = ['paid_at', 'voided_at'];

    public $belongsTo = [
        'student'  => [Student::class, 'key' => 'student_id'],
        'guardian' => [Guardian::class, 'key' => 'guardian_id'],
    ];

    public $hasMany = ['items' => [PaymentItem::class, 'key' => 'payment_id']];

    public const METHODS = ['cash' => 'Efectivo', 'qr' => 'QR', 'transfer' => 'Transferencia', 'deposit' => 'Depósito'];

    public function getMethodOptions(): array
    {
        return self::METHODS;
    }

    public function getStudentNameAttribute(): string
    {
        return (string) $this->student?->full_name;
    }
}
