<?php namespace Aero\Church\Classes;

use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\EventRegistration;
use Aero\Church\Models\Member;
use Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inscripciones con cupo y lista de espera, y control de asistencia. Toda mutación
 * de cupos ocurre dentro de una transacción con el evento bloqueado.
 */
class EventService
{
    public function register(ChurchEvent $event, ?Member $member, ?string $guestName = null, ?string $guestPhone = null): EventRegistration
    {
        $guestName = trim((string) $guestName);
        if (!$member && $guestName === '') {
            throw new ChurchException('Indica un miembro o el nombre del invitado.');
        }

        $reg = DB::transaction(function () use ($event, $member, $guestName, $guestPhone) {
            $e = ChurchEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($member && $member->tenant_id !== $e->tenant_id) {
                throw new ChurchException('El miembro y el evento pertenecen a iglesias distintas.');
            }
            if (!$e->isOpenForRegistration()) {
                throw new ChurchException('Este evento ya no admite inscripciones.');
            }
            if ($member && $e->registrations()->where('member_id', $member->id)->whereIn('status', ['registered', 'waitlist'])->exists()) {
                throw new ChurchException('Ya está inscrito en este evento.');
            }

            return EventRegistration::create([
                'tenant_id'   => $e->tenant_id,
                'event_id'    => $e->id,
                'member_id'   => $member?->id,
                'guest_name'  => $member ? null : $guestName,
                'guest_phone' => $member ? null : ($guestPhone ? preg_replace('/[^\d+]/', '', $guestPhone) : null),
                'status'      => $e->is_full ? 'waitlist' : 'registered',
                'token'       => $this->newToken(),
            ]);
        });

        Event::fire($reg->status === 'registered' ? 'aero.church.eventRegistered' : 'aero.church.eventWaitlisted', [$reg]);

        return $reg;
    }

    /** Cancela y, si liberó un cupo, sube al primero de la lista de espera. Devuelve quien subió. */
    public function cancel(EventRegistration $reg): ?EventRegistration
    {
        $promoted = DB::transaction(function () use ($reg) {
            $e = ChurchEvent::whereKey($reg->event_id)->lockForUpdate()->firstOrFail();
            $r = EventRegistration::whereKey($reg->id)->lockForUpdate()->firstOrFail();

            if ($r->status === 'cancelled') {
                return null;
            }
            $wasSeated = $r->status === 'registered';
            $r->status = 'cancelled';
            $r->save();

            if (!$wasSeated || $e->is_full) {
                return null;
            }

            $next = $e->registrations()->where('status', 'waitlist')->orderBy('id')->first();
            if ($next) {
                $next->status = 'registered';
                $next->save();
            }

            return $next;
        });

        Event::fire('aero.church.eventRegistrationCancelled', [$reg->fresh()]);
        if ($promoted) {
            Event::fire('aero.church.eventWaitlistPromoted', [$promoted]);
        }

        return $promoted;
    }

    public function checkIn(EventRegistration $reg): EventRegistration
    {
        if ($reg->status !== 'registered') {
            throw new ChurchException('Solo se puede marcar la llegada de quien tiene cupo confirmado.');
        }
        if (!$reg->checked_in_at) {
            $reg->checked_in_at = now();
            $reg->save();
            Event::fire('aero.church.eventCheckedIn', [$reg]);
        }

        return $reg;
    }

    /** Escaneo del QR personal: el token debe ser de una inscripción de ESTE evento. */
    public function checkInByToken(ChurchEvent $event, string $token): EventRegistration
    {
        $reg = $event->registrations()->where('token', trim($token))->first();
        if (!$reg) {
            throw new ChurchException('Código no válido para este evento.');
        }

        return $this->checkIn($reg);
    }

    public function undoCheckIn(EventRegistration $reg): void
    {
        $reg->checked_in_at = null;
        $reg->save();
    }

    /** Llegada sin inscripción previa: ignora el cupo (la persona ya está en la puerta). */
    public function walkIn(ChurchEvent $event, ?Member $member, ?string $guestName = null): EventRegistration
    {
        if ($member) {
            $existing = $event->registrations()->where('member_id', $member->id)->whereIn('status', ['registered', 'waitlist'])->first();
            if ($existing) {
                $existing->status = 'registered';
                $existing->save();

                return $this->checkIn($existing);
            }
        } elseif (trim((string) $guestName) === '') {
            throw new ChurchException('Indica un miembro o el nombre del invitado.');
        }

        $reg = EventRegistration::create([
            'tenant_id'  => $event->tenant_id,
            'event_id'   => $event->id,
            'member_id'  => $member?->id,
            'guest_name' => $member ? null : trim((string) $guestName),
            'status'     => 'registered',
            'token'      => $this->newToken(),
        ]);

        return $this->checkIn($reg);
    }

    protected function newToken(): string
    {
        do {
            $t = Str::lower(Str::random(20));
        } while (EventRegistration::where('token', $t)->exists());

        return $t;
    }
}
