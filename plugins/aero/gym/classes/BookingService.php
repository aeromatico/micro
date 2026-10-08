<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\Booking;
use Aero\Gym\Models\ClassSession;
use Aero\Gym\Models\GymSettings;
use Aero\Gym\Models\Member;
use Event;
use Illuminate\Support\Facades\DB;

/**
 * Reservas con cupo y lista de espera. Toda mutación toca (`touch`) la sesión:
 * esa marca es la que ve la pantalla en vivo para saber que algo cambió.
 */
class BookingService
{
    public function book(Member $member, ClassSession $session): Booking
    {
        if (!GymSettings::isEnabled($member->tenant_id)) {
            throw new GymException('El gimnasio tiene las reservas desactivadas.');
        }

        $booking = DB::transaction(function () use ($member, $session) {
            $s = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($s->tenant_id !== $member->tenant_id) {
                throw new GymException('El socio y la clase pertenecen a gimnasios distintos.');
            }
            if ($s->status !== 'scheduled' || $s->ends_at->isPast()) {
                throw new GymException('Esta clase ya no admite reservas.');
            }
            if ($member->status !== 'active') {
                throw new GymException('El socio no está activo.');
            }

            $membership = $member->memberships()->where('status', 'active')
                ->whereDate('starts_on', '<=', $s->starts_at->toDateString())
                ->whereDate('ends_on', '>=', $s->starts_at->toDateString())
                ->orderByDesc('ends_on')->first();
            if (!$membership) {
                throw new GymException('El socio no tiene una membresía vigente para la fecha de la clase.');
            }

            if (Booking::where('session_id', $s->id)->where('member_id', $member->id)
                ->whereIn('status', ['booked', 'waitlist', 'attended'])->exists()) {
                throw new GymException('El socio ya está inscrito en esta clase.');
            }

            if ($limit = $membership->plan?->classes_per_week) {
                $used = Booking::where('member_id', $member->id)
                    ->whereIn('status', ['booked', 'attended'])
                    ->whereHas('session', fn ($q) => $q->whereBetween('starts_at', [
                        $s->starts_at->copy()->startOfWeek(), $s->starts_at->copy()->endOfWeek(),
                    ]))->count();
                if ($used >= $limit) {
                    throw new GymException("Su plan permite {$limit} clases por semana y ya las usó.");
                }
            }

            $seated = Booking::where('session_id', $s->id)->whereIn('status', Booking::SEATED)->count();

            $b = Booking::create([
                'tenant_id'  => $s->tenant_id,
                'session_id' => $s->id,
                'member_id'  => $member->id,
                'status'     => $seated < $s->capacity ? 'booked' : 'waitlist',
                'booked_at'  => now(),
            ]);
            $s->touch();

            return $b;
        });

        Event::fire($booking->status === 'booked' ? 'aero.gym.classBooked' : 'aero.gym.waitlistJoined', [$booking]);

        return $booking;
    }

    public function cancel(Booking $booking, bool $enforceWindow = true): Booking
    {
        $wasSeated = DB::transaction(function () use ($booking, $enforceWindow) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (!in_array($b->status, ['booked', 'waitlist'], true)) {
                throw new GymException('Esta reserva ya no se puede cancelar.');
            }

            $session = ClassSession::findOrFail($b->session_id);
            if ($enforceWindow && $b->status === 'booked') {
                $hours = GymSettings::forTenant($b->tenant_id)->cancel_window_hours ?? 2;
                if (now()->greaterThan($session->starts_at->copy()->subHours($hours))) {
                    throw new GymException("Solo se puede cancelar hasta {$hours} h antes de la clase.");
                }
            }

            $seated = $b->status === 'booked';
            $b->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $session->touch();
            $booking->setRawAttributes($b->getAttributes(), true);

            return $seated;
        });

        Event::fire('aero.gym.classCancelled', [$booking]);

        if ($wasSeated) {
            $this->promoteWaitlist(ClassSession::findOrFail($booking->session_id));
        }

        return $booking;
    }

    /** Sube a la lista de espera tantos socios como cupos libres haya. */
    public function promoteWaitlist(ClassSession $session): int
    {
        if (!(GymSettings::forTenant($session->tenant_id)->waitlist_auto_promote ?? true)) {
            return 0;
        }

        $promoted = [];
        DB::transaction(function () use ($session, &$promoted) {
            $s = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($s->status !== 'scheduled' || $s->ends_at->isPast()) {
                return;
            }
            $free = $s->capacity - Booking::where('session_id', $s->id)->whereIn('status', Booking::SEATED)->count();

            while ($free-- > 0) {
                $next = Booking::where('session_id', $s->id)->where('status', 'waitlist')
                    ->orderBy('booked_at')->orderBy('id')->first();
                if (!$next) {
                    break;
                }
                $next->update(['status' => 'booked']);
                $promoted[] = $next;
            }
            if ($promoted) {
                $s->touch();
            }
        });

        foreach ($promoted as $b) {
            Event::fire('aero.gym.waitlistPromoted', [$b]);
        }

        return count($promoted);
    }

    public function checkIn(Booking $booking): Booking
    {
        if ($booking->status !== 'booked') {
            throw new GymException('Solo una reserva confirmada puede marcar asistencia.');
        }
        $booking->update(['status' => 'attended', 'checked_in_at' => now()]);
        $booking->session?->touch();
        Event::fire('aero.gym.attendanceMarked', [$booking]);

        return $booking;
    }

    public function markNoShow(Booking $booking): Booking
    {
        if ($booking->status !== 'booked') {
            throw new GymException('Solo una reserva confirmada puede marcarse como inasistencia.');
        }
        $booking->update(['status' => 'no_show']);
        $booking->session?->touch();

        return $booking;
    }

    /** Cancela la clase completa y avisa a quienes tenían cupo o espera. */
    public function cancelSession(ClassSession $session): int
    {
        $n = 0;
        DB::transaction(function () use ($session, &$n) {
            $session->update(['status' => 'cancelled']);
            $n = Booking::where('session_id', $session->id)->whereIn('status', ['booked', 'waitlist'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $session->touch();
        });
        Event::fire('aero.gym.sessionCancelled', [$session]);

        return $n;
    }
}
