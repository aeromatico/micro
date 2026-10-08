<?php namespace Aero\Credits\Models;

use Model;

/**
 * Cuánto puede invitar un tenant y con qué regalo (plan + periodo) se
 * beneficia cada invitado. El superadmin la edita en cualquier momento desde
 * Créditos → Cuotas de invitación; si no existe fila para un tenant, se crea
 * al vuelo con los valores por defecto de Settings (ver
 * Aero\Credits\Classes\Invitations::quotaFor()).
 */
class CreditInvitationQuota extends Model
{
    public $table = 'aero_credits_invitation_quotas';

    public $fillable = [
        'tenant_id', 'invites_allowed', 'grant_plan_id', 'grant_period_unit', 'grant_period_count',
    ];

    protected $casts = [
        'invites_allowed'    => 'integer',
        'grant_period_count' => 'integer',
    ];

    public function getPeriodUnitOptions(): array
    {
        return ['' => '— Usar el valor por defecto —', 'daily' => 'Días', 'monthly' => 'Meses', 'annual' => 'Años'];
    }

    public function getGrantPeriodUnitOptions(): array
    {
        return $this->getPeriodUnitOptions();
    }

    public function getGrantPlanIdOptions(): array
    {
        return ['' => '— Usar el valor por defecto —'] + (class_exists(\Aero\Sites\Models\Plan::class)
            ? \Aero\Sites\Models\Plan::orderBy('sort_order')->pluck('name', 'id')->all()
            : []);
    }

    public function getTenantNameAttribute(): string
    {
        if (class_exists(\Aero\Sites\Models\Tenant::class)) {
            $name = \Aero\Sites\Models\Tenant::where('id', $this->tenant_id)->value('name');

            if ($name) {
                return $name;
            }
        }

        return "Tenant #{$this->tenant_id}";
    }
}
