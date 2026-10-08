<?php namespace Aero\Credits\Models;

use Model;

/**
 * Un código de invitación enviado por un tenant a un prospecto (correo o
 * WhatsApp, ver Aero\Credits\Classes\Invitations). Al canjearse crea/activa
 * un tenant nuevo con el plan/periodo configurado en la cuota del que
 * invitó. `tenant_id` = quien invita; `redeemed_by_tenant_id` = el nuevo.
 */
class CreditInvitation extends Model
{
    public $table = 'aero_credits_invitations';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_SENT     = 'sent';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED  = 'expired';
    public const STATUS_REVOKED  = 'revoked';

    public $fillable = [
        'code', 'tenant_id', 'invited_by_user_id', 'channel', 'recipient',
        'plan_id', 'period_unit', 'period_count', 'status',
        'redeemed_by_tenant_id', 'redeemed_at', 'sent_at', 'expires_at',
    ];

    protected $dates = ['redeemed_at', 'sent_at', 'expires_at'];

    protected $casts = [
        'period_count' => 'integer',
    ];

    public function scopeRedeemable($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function getChannelOptions(): array
    {
        return ['whatsapp' => 'WhatsApp', 'email' => 'Correo'];
    }

    /** "7 días gratis con todas las funciones del plan PRO" */
    public function grantLabel(): string
    {
        $plan = ($this->plan_id && class_exists(\Aero\Sites\Models\Plan::class)) ? \Aero\Sites\Models\Plan::find($this->plan_id) : null;
        $label = \Aero\Credits\Classes\Grants::periodLabel((string) $this->period_unit, (int) $this->period_count) . ' gratis';

        return $plan && $plan->is_pro ? "{$label} con todas las funciones del plan PRO" : $label;
    }

    public function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING  => 'Pendiente',
            self::STATUS_SENT     => 'Enviada',
            self::STATUS_REDEEMED => 'Canjeada',
            self::STATUS_EXPIRED  => 'Vencida',
            self::STATUS_REVOKED  => 'Revocada',
        ];
    }

    public function getTenantNameAttribute(): string
    {
        return $this->resolveTenantName($this->tenant_id);
    }

    public function getRedeemedByTenantNameAttribute(): string
    {
        return $this->redeemed_by_tenant_id ? $this->resolveTenantName($this->redeemed_by_tenant_id) : '';
    }

    protected function resolveTenantName(?int $tenantId): string
    {
        if (!$tenantId) {
            return '';
        }

        if (class_exists(\Aero\Sites\Models\Tenant::class)) {
            $name = \Aero\Sites\Models\Tenant::where('id', $tenantId)->value('name');

            if ($name) {
                return $name;
            }
        }

        return "Tenant #{$tenantId}";
    }
}
