<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

class ClassSession extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_sessions';

    public $fillable = ['tenant_id', 'class_type_id', 'instructor_id', 'schedule_id', 'starts_at', 'ends_at', 'capacity', 'room', 'status'];

    protected $dates = ['starts_at', 'ends_at'];

    public $rules = [
        'class_type_id' => 'required|integer',
        'starts_at'     => 'required|date',
        'capacity'      => 'required|integer|min:1',
        'status'        => 'in:scheduled,cancelled,done',
    ];

    public $belongsTo = [
        'classType'  => [ClassType::class, 'key' => 'class_type_id'],
        'instructor' => [Instructor::class, 'key' => 'instructor_id'],
    ];

    public $hasMany = ['bookings' => [Booking::class, 'key' => 'session_id']];

    public function getStatusOptions(): array
    {
        return ['scheduled' => 'Programada', 'cancelled' => 'Cancelada', 'done' => 'Realizada'];
    }

    public function getClassTypeIdOptions(): array
    {
        return ClassType::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getInstructorIdOptions(): array
    {
        return ['' => '— Sin instructor —'] + Instructor::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['class_type_id' => ClassType::class, 'instructor_id' => Instructor::class]);
        $type = $this->class_type_id ? ClassType::find($this->class_type_id) : null;
        if ($type) {
            if (empty($this->tenant_id)) {
                $this->tenant_id = $type->tenant_id;
            }
            if (empty($this->capacity)) {
                $this->capacity = $type->default_capacity;
            }
            if (empty($this->ends_at) && $this->starts_at) {
                $this->ends_at = \Carbon\Carbon::parse($this->starts_at)->addMinutes($type->duration_minutes);
            }
        }
    }

    public function scopeUpcoming($q)
    {
        return $q->where('ends_at', '>=', now());
    }

    public function bookedCount(): int
    {
        return $this->bookings()->whereIn('status', ['booked', 'attended'])->count();
    }

    public function waitlistCount(): int
    {
        return $this->bookings()->where('status', 'waitlist')->count();
    }
}
