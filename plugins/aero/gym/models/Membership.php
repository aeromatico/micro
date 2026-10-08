<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

class Membership extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_memberships';

    public $fillable = [
        'tenant_id', 'member_id', 'plan_id', 'starts_on', 'ends_on', 'status', 'price', 'currency',
        'paid_at', 'payment_reference', 'renewed_from_id', 'cancel_reason',
    ];

    protected $dates = ['starts_on', 'ends_on', 'paid_at', 'cancelled_at'];

    protected $jsonable = ['reminders_sent'];

    public $rules = [
        'member_id' => 'required|integer',
        'starts_on' => 'required|date',
        'ends_on'   => 'required|date|after_or_equal:starts_on',
        'status'    => 'in:pending,active,expired,cancelled',
    ];

    public $belongsTo = [
        'member' => [Member::class, 'key' => 'member_id'],
        'plan'   => [Plan::class, 'key' => 'plan_id'],
    ];

    public function getStatusOptions(): array
    {
        return ['pending' => 'Pendiente de pago', 'active' => 'Activa', 'expired' => 'Vencida', 'cancelled' => 'Cancelada'];
    }

    public function getPlanIdOptions(): array
    {
        return Plan::visible()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getMemberIdOptions(): array
    {
        return Member::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Al elegir plan sin precio/fecha fin, se completan desde el plan. */
    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['member_id' => Member::class, 'plan_id' => Plan::class]);
        if ($this->plan_id && ($plan = Plan::find($this->plan_id))) {
            if ($this->price === null || $this->price === '') {
                $this->price = $plan->price;
            }
            if (empty($this->ends_on) && $this->starts_on) {
                $this->ends_on = \Carbon\Carbon::parse($this->starts_on)->addDays($plan->duration_days - 1)->toDateString();
            }
        }
        if (empty($this->member_id) === false && empty($this->tenant_id)) {
            $this->tenant_id = Member::withTrashed()->whereKey($this->member_id)->value('tenant_id');
        }
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function getDaysLeftAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->ends_on->copy()->startOfDay(), false);
    }
}
