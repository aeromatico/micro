<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\GymSettings;
use Aero\Gym\Models\Member;
use Aero\Gym\Models\Membership;
use Aero\Gym\Models\Plan;
use Carbon\Carbon;
use Event;

class MembershipService
{
    /** Crea la membresía. Si el socio sigue vigente, la nueva empieza al terminar la actual. */
    public function create(Member $member, Plan $plan, ?Carbon $startsOn = null, bool $paid = false, ?Membership $renewing = null): Membership
    {
        if ($plan->tenant_id !== $member->tenant_id) {
            throw new GymException('El plan y el socio pertenecen a gimnasios distintos.');
        }

        if (!$startsOn) {
            $last = $member->memberships()->whereIn('status', ['active', 'pending'])->max('ends_on');
            $startsOn = $last && Carbon::parse($last)->gte(today()) ? Carbon::parse($last)->addDay() : today();
        }

        $m = Membership::create([
            'tenant_id'       => $member->tenant_id,
            'member_id'       => $member->id,
            'plan_id'         => $plan->id,
            'starts_on'       => $startsOn->toDateString(),
            'ends_on'         => $startsOn->copy()->addDays($plan->duration_days - 1)->toDateString(),
            'status'          => 'pending',
            'price'           => $plan->price,
            'currency'        => GymSettings::forTenant($member->tenant_id)->currency ?: 'BOB',
            'renewed_from_id' => $renewing?->id,
        ]);

        if ($paid) {
            $this->markPaid($m);
        }

        return $m;
    }

    public function markPaid(Membership $m, ?string $reference = null): Membership
    {
        if (in_array($m->status, ['active'], true)) {
            return $m;
        }
        if ($m->status === 'cancelled') {
            throw new GymException('La membresía está cancelada.');
        }

        $m->status = 'active';
        $m->paid_at = now();
        $m->payment_reference = $reference ?: $m->payment_reference;
        $m->save();
        Event::fire('aero.gym.membershipActivated', [$m]);

        return $m;
    }

    public function cancel(Membership $m, ?string $reason = null): Membership
    {
        $m->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        Event::fire('aero.gym.membershipCancelled', [$m]);

        return $m;
    }

    /** Genera (o reutiliza) el QR de cobro de aero/pay. Null si no hay Pay o cuenta configurada. */
    public function issueCharge(Membership $m): ?\Aero\Pay\Models\QrCode
    {
        if (!class_exists(\Aero\Pay\Classes\QrIssuer::class) || $m->status === 'active') {
            return null;
        }

        if ($m->payment_reference) {
            $existing = \Aero\Pay\Models\QrCode::where('internal_reference', $m->payment_reference)->first();
            if ($existing && $existing->status === 'pending') {
                return $existing;
            }
        }

        $settings = GymSettings::where('tenant_id', $m->tenant_id)->first();
        if (!$settings || !$settings->bank_account_id) {
            return null;
        }

        $bank = \Aero\Pay\Models\BankAccount::active()->find($settings->bank_account_id);
        if (!$bank) {
            return null;
        }

        try {
            $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $bank,
                amount: (float) $m->price,
                currency: $m->currency ?: 'BOB',
                description: 'Membresía ' . ($m->plan?->name ?? '') . ' — ' . ($m->member?->name ?? ''),
                externalReference: 'gym-membresia-' . $m->id . '-' . now()->timestamp,
                origin: 'backend',
                dueDate: now()->addDays(30)->toDateString(),
                tenantId: $m->tenant_id,
            );
        } catch (\Throwable $e) {
            \Log::warning('[gym] No se pudo emitir el QR: ' . $e->getMessage());

            return null;
        }

        $m->payment_reference = $qr->internal_reference;
        $m->save();

        return $qr;
    }

    /** Activa las membresías cuyo QR ya se pagó. Fallback del listener de QrCode. */
    public function syncPayments(): int
    {
        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return 0;
        }

        $n = 0;
        Membership::where('status', 'pending')->whereNotNull('payment_reference')->get()->each(function ($m) use (&$n) {
            if (\Aero\Pay\Models\QrCode::where('internal_reference', $m->payment_reference)->where('status', 'paid')->exists()) {
                $this->markPaid($m);
                $n++;
            }
        });

        return $n;
    }

    /** Vence las activas cuyo fin (+gracia) ya pasó. Devuelve cuántas. */
    public function expireDue(): int
    {
        $n = 0;
        Membership::where('status', 'active')->whereDate('ends_on', '<', today())->get()->each(function ($m) use (&$n) {
            $grace = (int) (GymSettings::forTenant($m->tenant_id)->grace_days ?? 0);
            if ($m->ends_on->copy()->addDays($grace)->lt(today())) {
                $m->update(['status' => 'expired']);
                Event::fire('aero.gym.membershipExpired', [$m]);
                $n++;
            }
        });

        return $n;
    }
}
