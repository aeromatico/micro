<?php namespace Aero\Church\Models;

use Aero\Church\Classes\PrayerAccess;
use Model;

/**
 * Petición de oración. Información sensible: solo la ven la dirección pastoral y el responsable
 * asignado; nunca aparece en listados generales ni en el resumen.
 */
class Prayer extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const STATUSES = ['open' => 'Abierta', 'in_prayer' => 'En oración', 'answered' => 'Respondida', 'closed' => 'Cerrada'];

    public $table = 'aero_church_prayers';

    public $attributes = ['status' => 'open', 'is_private' => true, 'source' => 'manual', 'wants_contact' => false];

    public $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'assigned_user_id', 'name', 'phone', 'request', 'is_private', 'wants_contact',
        'consent_data', 'status', 'source',
    ];

    public $nullable = ['branch_id', 'member_id', 'assigned_user_id'];

    public $rules = [
        'request' => 'required|string|max:2000',
        'status'  => 'required|in:open,in_prayer,answered,closed',
    ];

    public $dates = ['answered_at'];

    public $belongsTo = [
        'member' => [Member::class, 'key' => 'member_id'],
        'branch' => [Branch::class, 'key' => 'branch_id'],
    ];

    public $hasMany = [
        'notes' => [PrayerNote::class, 'key' => 'prayer_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['branch_id' => Branch::class, 'member_id' => Member::class]);

        if (\BackendAuth::getUser() && $this->assigned_user_id && !array_key_exists($this->assigned_user_id, $this->getAssignedUserIdOptions())) {
            throw new \ApplicationException('El responsable no es válido.');
        }
    }

    public function beforeSave(): void
    {
        $this->answered_at = $this->status === 'answered' ? ($this->answered_at ?: now()) : null;
    }

    /** Lo que el usuario del panel puede ver: su iglesia y, si es solo responsable, lo asignado a él. */
    public function scopeManaged($query)
    {
        $query = $this->scopeVisible($query);
        $restricted = PrayerAccess::restrictedUserId();

        return $restricted === null ? $query : $query->where($this->getTable() . '.assigned_user_id', $restricted ?: 0);
    }

    public function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getMemberIdOptions(): array
    {
        return Member::visible()->orderBy('first_name')->get()->pluck('full_name', 'id')->all();
    }

    /** Usuarios de la misma iglesia con permiso pastoral o de líder. */
    public function getAssignedUserIdOptions(): array
    {
        $q = \Backend\Models\User::orderBy('login');
        if (!\Aero\Church\Classes\CurrentTenant::isAdmin()) {
            $tenantId = \Aero\Church\Classes\CurrentTenant::id();
            $ids = $tenantId && \Schema::hasTable('aero_sites_tenant_users')
                ? \DB::table('aero_sites_tenant_users')->where('tenant_id', $tenantId)->pluck('user_id')
                : collect();
            $q->whereIn('id', $ids);
        }

        return $q->get()->filter(fn ($u) => $u->hasAnyAccess(['aero.church.use', 'aero.church.pastor', 'aero.church.leader']))
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name) . ' (' . $u->login . ')'])->all();
    }
}
