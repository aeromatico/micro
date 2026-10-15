<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

class Charge extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_school_charges';

    public $fillable = [
        'tenant_id', 'student_id', 'year_id', 'fee_plan_id', 'kind', 'concept', 'period', 'due_on', 'amount', 'discount',
        'paid_amount', 'status', 'paid_at', 'payment_reference', 'reminders_sent', 'notes',
    ];

    public $rules = [
        'student_id' => 'required|integer',
        'concept'    => 'required|string|max:255',
        'amount'     => 'required|numeric|min:0',
    ];

    public $jsonable = ['reminders_sent'];

    public $dates = ['due_on', 'paid_at'];

    public $belongsTo = [
        'student' => [Student::class, 'key' => 'student_id'],
        'feePlan' => [FeePlan::class, 'key' => 'fee_plan_id'],
    ];

    public function beforeValidate(): void
    {
        foreach (['discount', 'paid_amount'] as $f) {
            if ($this->{$f} === '' || $this->{$f} === null) {
                $this->{$f} = 0;
            }
        }
        if ($this->student_id && !$this->tenant_id) {
            $this->tenant_id = Student::whereKey($this->student_id)->value('tenant_id');
        }
    }

    public function getStudentIdOptions(): array
    {
        return Student::visible()->orderBy('paternal_surname')->orderBy('first_name')->get()->pluck('full_name', 'id')->all();
    }

    public function getKindOptions(): array
    {
        return FeePlan::KINDS;
    }

    public function getStatusOptions(): array
    {
        return ['pending' => 'Pendiente', 'partial' => 'Pago parcial', 'paid' => 'Pagada', 'void' => 'Anulada'];
    }

    public function getNetAttribute(): float
    {
        return round((float) $this->amount - (float) $this->discount, 2);
    }

    public function getDueAttribute(): float
    {
        return in_array($this->status, ['paid', 'void'], true) ? 0.0 : max(0.0, round($this->net - (float) $this->paid_amount, 2));
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['pending', 'partial'], true) && $this->due_on && $this->due_on->lt(today());
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['pending', 'partial']);
    }

    public function scopeOverdue($q)
    {
        return $q->whereIn('status', ['pending', 'partial'])->whereDate('due_on', '<', today());
    }

    public function getStudentNameAttribute(): string
    {
        return (string) $this->student?->full_name;
    }
}
