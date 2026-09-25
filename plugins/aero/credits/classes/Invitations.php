<?php namespace Aero\Credits\Classes;

use Aero\Credits\Models\CreditInvitation;
use Aero\Credits\Models\CreditInvitationQuota;
use Aero\Credits\Models\Settings;
use DB;
use Str;

/**
 * Invitaciones de tenant a tenant. Cada tenant tiene una cuota
 * (`CreditInvitationQuota`) — por defecto 3, la misma después de un setup
 * normal o de haber sido invitado — que el superadmin puede reasignar en
 * cualquier momento (cuántas invitaciones y con qué plan/periodo gratis se
 * benefician los invitados). El código se manda por WhatsApp (por defecto,
 * vía Aero.Hello si está instalado) o correo.
 */
class Invitations
{
    /** Cuota del tenant; se crea al vuelo con los valores por defecto si no existe. */
    public static function quotaFor(int $tenantId): CreditInvitationQuota
    {
        return CreditInvitationQuota::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['invites_allowed' => Settings::defaultInviteQuota()]
        );
    }

    public static function setQuota(int $tenantId, array $attrs): CreditInvitationQuota
    {
        $quota = static::quotaFor($tenantId);
        $quota->fill(array_intersect_key($attrs, array_flip([
            'invites_allowed', 'grant_plan_id', 'grant_period_unit', 'grant_period_count',
        ])));
        $quota->save();

        return $quota;
    }

    /** Invitaciones que el tenant ya usó (todo lo que no sea revocada no libera cupo). */
    public static function used(int $tenantId): int
    {
        return CreditInvitation::where('tenant_id', $tenantId)
            ->where('status', '!=', CreditInvitation::STATUS_REVOKED)
            ->count();
    }

    public static function remaining(int $tenantId): int
    {
        $quota = static::quotaFor($tenantId);

        return max(0, $quota->invites_allowed - static::used($tenantId));
    }

    /**
     * Crea y envía una invitación. `$channel` default whatsapp;
     * `$recipient` es el teléfono (WhatsApp) o el correo.
     */
    public static function create(int $tenantId, ?int $userId, string $channel, string $recipient): CreditInvitation
    {
        if (static::remaining($tenantId) <= 0) {
            throw new \RuntimeException('No te quedan invitaciones disponibles.');
        }

        $channel = in_array($channel, ['whatsapp', 'email'], true) ? $channel : 'whatsapp';
        $recipient = trim($recipient);

        if ($recipient === '') {
            throw new \RuntimeException($channel === 'email' ? 'Indica un correo.' : 'Indica un número de WhatsApp.');
        }

        $quota = static::quotaFor($tenantId);
        $planId = $quota->grant_plan_id ?: Settings::defaultInvitePlanId();
        $periodUnit = $quota->grant_period_unit ?: Settings::defaultInvitePeriodUnit();
        $periodCount = $quota->grant_period_count ?: Settings::defaultInvitePeriodCount();

        $invitation = CreditInvitation::create([
            'code'               => static::generateCode(),
            'tenant_id'          => $tenantId,
            'invited_by_user_id' => $userId,
            'channel'            => $channel,
            'recipient'          => $recipient,
            'plan_id'            => $planId,
            'period_unit'        => $periodUnit,
            'period_count'       => $periodCount,
            'status'             => CreditInvitation::STATUS_PENDING,
            'expires_at'         => now()->addDays(30),
        ]);

        static::send($invitation);

        return $invitation;
    }

    public static function send(CreditInvitation $invitation): void
    {
        $link = static::signupUrl($invitation->code);
        $tenantName = class_exists(\Aero\Sites\Models\Tenant::class)
            ? (\Aero\Sites\Models\Tenant::find($invitation->tenant_id)->name ?? 'un cliente de Market')
            : 'un cliente de Market';

        $message = "{$tenantName} te invitó a Market. Crea tu sitio gratis con este código: {$invitation->code}\n{$link}";

        try {
            if ($invitation->channel === 'whatsapp' && class_exists(\Aero\Hello\Classes\Hello::class)) {
                \Aero\Hello\Classes\Hello::send($invitation->recipient, $message);
            }
            elseif ($invitation->channel === 'email') {
                \Mail::raw($message, function ($mail) use ($invitation) {
                    $mail->to($invitation->recipient)->subject('Te invitaron a Market');
                });
            }

            $invitation->status = CreditInvitation::STATUS_SENT;
            $invitation->sent_at = now();
            $invitation->save();
        }
        catch (\Throwable $e) {
            \Log::error("Aero.Credits: fallo al enviar la invitación #{$invitation->id}: " . $e->getMessage());
        }
    }

    /** @return array{type:string,model:CreditInvitation,plan:\Aero\Sites\Models\Plan,period_unit:string,period_count:int}|null */
    public static function preview(string $code): ?array
    {
        if (!class_exists(\Aero\Sites\Models\Plan::class)) {
            return null;
        }

        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $invitation = CreditInvitation::redeemable()->where('code', $code)->first();
        if (!$invitation) {
            return null;
        }

        $plan = $invitation->plan_id ? \Aero\Sites\Models\Plan::active()->find($invitation->plan_id) : null;
        if (!$plan) {
            return null;
        }

        return [
            'type'         => 'invitation',
            'model'        => $invitation,
            'plan'         => $plan,
            'period_unit'  => $invitation->period_unit,
            'period_count' => (int) $invitation->period_count,
        ];
    }

    public static function consume(CreditInvitation $invitation, \Aero\Sites\Models\Tenant $tenant): void
    {
        DB::transaction(function () use ($invitation, $tenant) {
            $locked = CreditInvitation::whereKey($invitation->id)->lockForUpdate()->first();

            if (!$locked || !in_array($locked->status, [CreditInvitation::STATUS_PENDING, CreditInvitation::STATUS_SENT], true)) {
                throw new \RuntimeException('Invitación no válida.');
            }

            if ($locked->expires_at && $locked->expires_at->isPast()) {
                throw new \RuntimeException('Invitación vencida.');
            }

            $locked->status = CreditInvitation::STATUS_REDEEMED;
            $locked->redeemed_by_tenant_id = $tenant->id;
            $locked->redeemed_at = now();
            $locked->save();

            $plan = \Aero\Sites\Models\Plan::find($locked->plan_id);
            if ($plan) {
                Grants::apply($tenant, $plan, $locked->period_unit, (int) $locked->period_count);
            }
        });

        // El invitado hereda la cuota estándar (3) apenas alguien la consulte — ver quotaFor().
    }

    protected static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (CreditInvitation::where('code', $code)->exists());

        return $code;
    }

    protected static function signupUrl(string $code): string
    {
        $base = class_exists(\Aero\Sites\Models\Settings::class)
            ? (\Aero\Sites\Models\Settings::get('public_signup_url') ?: url('/alta'))
            : url('/alta');

        return rtrim($base, '/') . '?promo=' . urlencode($code);
    }
}
