<?php namespace Aero\Church\Models;

use Aero\Church\Classes\GroupAccess;
use Model;

/** Ministerio, célula o grupo pequeño. Privado por tenant; el líder del panel solo ve los suyos. */
class Group extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const TYPES = [
        'ministry' => 'Ministerio', 'cell' => 'Célula', 'youth' => 'Jóvenes', 'children' => 'Niños', 'other' => 'Otro',
    ];

    public const DAYS = [
        'mon' => 'Lunes', 'tue' => 'Martes', 'wed' => 'Miércoles', 'thu' => 'Jueves', 'fri' => 'Viernes', 'sat' => 'Sábado', 'sun' => 'Domingo',
    ];

    public $table = 'aero_church_groups';

    public static $audited = true;
    public static $auditLabel = 'grupo';
    public static $auditType = 'group';

    public $attributes = ['type' => 'ministry', 'is_active' => true];

    public $fillable = [
        'tenant_id', 'branch_id', 'backend_user_id', 'leader_id', 'name', 'type', 'description',
        'meeting_day', 'meeting_time', 'location', 'is_active',
    ];

    public $rules = [
        'name' => 'required|string|max:255',
        'type' => 'required|in:ministry,cell,youth,children,other',
    ];

    public $belongsTo = [
        'branch' => [Branch::class, 'key' => 'branch_id'],
        'leader' => [Member::class, 'key' => 'leader_id'],
    ];

    public $hasMany = [
        'memberships' => [GroupMember::class, 'key' => 'group_id'],
        'meetings'    => [Meeting::class, 'key' => 'group_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['branch_id' => Branch::class, 'leader_id' => Member::class]);

        if (\BackendAuth::getUser() && $this->backend_user_id && !array_key_exists($this->backend_user_id, $this->getBackendUserIdOptions())) {
            throw new \ApplicationException('El responsable no es válido.');
        }
    }

    /** Lo que el usuario del panel puede gestionar: su iglesia y, si es solo líder, sus grupos. */
    public function scopeManaged($query)
    {
        $query = $this->scopeVisible($query);
        $restricted = GroupAccess::restrictedUserId();

        return $restricted === null ? $query : $query->where($this->getTable() . '.backend_user_id', $restricted ?: 0);
    }

    public function getTypeOptions(): array
    {
        return self::TYPES;
    }

    public function getMeetingDayOptions(): array
    {
        return self::DAYS;
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getLeaderIdOptions(): array
    {
        return Member::visible()->orderBy('first_name')->get()->pluck('full_name', 'id')->all();
    }

    public function getBackendUserIdOptions(): array
    {
        $q = \Backend\Models\User::orderBy('login');
        if (!\Aero\Church\Classes\CurrentTenant::isAdmin()) {
            // Solo usuarios de la misma iglesia (tabla de usuarios por tenant de Sites); falla cerrado.
            $tenantId = \Aero\Church\Classes\CurrentTenant::id();
            $ids = $tenantId && \Schema::hasTable('aero_sites_tenant_users')
                ? \DB::table('aero_sites_tenant_users')->where('tenant_id', $tenantId)->pluck('user_id')
                : collect();
            $q->whereIn('id', $ids);
        }

        return $q->get()->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name) . ' (' . $u->login . ')'])->all();
    }

    /** Integrantes vigentes (sin fecha de salida). */
    public function activeMembers()
    {
        return $this->memberships()->whereNull('left_at')->with('member');
    }
}
