<?php namespace Aero\Tickets\Models;

use Backend\Models\User;
use Illuminate\Support\Facades\DB;

/** Usuario del panel visto desde la administración de la plataforma (lista, perfil, suspensión, rol global). */
class PlatformUser extends User
{
    public $table = 'backend_users';

    public function getFullNameLabelAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: (string) $this->login;
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->is_superuser ? 'Superadministrador' : ((string) $this->role?->name ?: '—');
    }

    public function getIsSuspendedLabelAttribute(): string
    {
        return $this->isSuspendedNow() ? 'Suspendido' : ($this->is_activated ? 'Activo' : 'Sin activar');
    }

    public function isSuspendedNow(): bool
    {
        return (bool) DB::table('backend_user_throttle')->where('user_id', $this->id)->where('is_suspended', 1)->exists();
    }

    /** Espacios de trabajo que este usuario administra (organizador). */
    public function tenantsOwned()
    {
        return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::where('backend_user_id', $this->id)->get() : collect();
    }

    public function getTenantsLabelAttribute(): string
    {
        return $this->tenantsOwned()->pluck('name')->implode(', ') ?: '—';
    }

    public function getRoleIdOptions(): array
    {
        return \Backend\Models\UserRole::where('code', 'like', 'tickets_%')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getRoleFilterOptions(): array
    {
        return \Backend\Models\UserRole::orderBy('name')->pluck('name', 'id')->all();
    }
}
