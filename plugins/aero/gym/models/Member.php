<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Illuminate\Support\Str;
use Model;

class Member extends Model
{
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_members';

    protected $dates = ['deleted_at'];

    public $attributes = ['status' => 'active'];

    public $fillable = ['tenant_id', 'user_id', 'name', 'phone', 'email', 'document', 'birthdate', 'card_number', 'status', 'notes'];

    public $rules = [
        'name'   => 'required|string|max:255',
        'status' => 'in:active,suspended,inactive',
    ];

    public $hasMany = [
        'memberships' => [Membership::class, 'key' => 'member_id'],
        'bookings'    => [Booking::class, 'key' => 'member_id'],
        'routines'    => [Routine::class, 'key' => 'member_id'],
        'measurements' => [Measurement::class, 'key' => 'member_id'],
    ];

    public $belongsToMany = [
        'groups' => [Group::class, 'table' => 'aero_gym_group_member', 'key' => 'member_id', 'otherKey' => 'group_id'],
    ];

    public function getGroupsOptions(): array
    {
        return Group::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getStatusOptions(): array
    {
        return ['active' => 'Activo', 'suspended' => 'Suspendido', 'inactive' => 'Inactivo'];
    }

    public function beforeCreate(): void
    {
        $this->qr_token = $this->qr_token ?: self::newToken();
    }

    public static function newToken(): string
    {
        do {
            $token = 'GYM' . strtoupper(Str::random(12));
        } while (static::withTrashed()->where('qr_token', $token)->exists());

        return $token;
    }

    /** Membresía que hoy da acceso (activa y dentro de su vigencia + gracia). */
    public function currentMembership(int $graceDays = 0): ?Membership
    {
        return $this->memberships()
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today()->subDays($graceDays))
            ->orderByDesc('ends_on')
            ->first();
    }

    public function getLastEndsOnAttribute(): ?string
    {
        return $this->memberships()->whereIn('status', ['active', 'expired'])->max('ends_on');
    }
}
