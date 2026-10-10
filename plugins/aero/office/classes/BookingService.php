<?php namespace Aero\Office\Classes;

use Aero\Office\Models\Booking;
use Aero\Office\Models\BookingLog;
use Aero\Office\Models\Branch;
use Aero\Office\Models\Customer;
use Aero\Office\Models\OfficeSettings;
use Aero\Office\Models\Service;
use Aero\Office\Models\Worker;
use Carbon\Carbon;
use Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Toda mutación de reservas pasa por aquí: valida contra Availability DENTRO
 * de una transacción que bloquea la fila del profesional (lockForUpdate), así
 * dos clientes simultáneos no pueden tomar el mismo horario. Cada cambio deja
 * historial en BookingLog y dispara aero.office.*.
 */
class BookingService
{
    private const TRANSITIONS = [
        'pending'    => ['confirmed', 'rejected', 'cancelled'],
        'confirmed'  => ['in_service', 'completed', 'cancelled', 'no_show'],
        'in_service' => ['completed', 'cancelled'],
    ];

    /**
     * @param array{branch_id:int,service_id:int,worker_id?:?int,starts_at:mixed,customer_notes?:?string,internal_notes?:?string,status?:?string} $data
     * @param string $source public|manual  (public respeta anticipación y servicios ocultos)
     */
    public function create(Customer $customer, array $data, string $source = 'manual', ?int $userId = null): Booking
    {
        $tenantId = (int) $customer->tenant_id;
        $public = $source === 'public';
        $start = Carbon::parse($data['starts_at'])->seconds(0);

        $branch = Branch::where('tenant_id', $tenantId)->active()->find($data['branch_id'] ?? 0);
        $service = Service::where('tenant_id', $tenantId)->active()->find($data['service_id'] ?? 0);
        if (!$branch || !$service) {
            throw new OfficeException('La sucursal o el servicio ya no están disponibles.');
        }
        if ($public && !$service->is_public) {
            throw new OfficeException('Este servicio no se puede reservar en línea.');
        }
        if (!OfficeSettings::isEnabled($tenantId)) {
            throw new OfficeException('Las reservas están desactivadas.');
        }
        if (!$branch->services()->where('aero_office_services.id', $service->id)->exists()) {
            throw new OfficeException('Ese servicio no se ofrece en la sucursal elegida.');
        }
        if ($public && $start->lt(now()->addHours((int) OfficeSettings::forTenant($tenantId)->min_notice_hours))) {
            throw new OfficeException('Ese horario ya no admite reservas por falta de anticipación.');
        }
        if ($public && $start->gt(now()->addDays((int) (OfficeSettings::forTenant($tenantId)->max_advance_days ?: 60))->endOfDay())) {
            throw new OfficeException('Todavía no se aceptan reservas para esa fecha.');
        }

        $candidates = $this->candidates($service, $branch, $data['worker_id'] ?? null, $public, $start);
        if ($candidates->isEmpty()) {
            throw new OfficeException('No hay profesionales disponibles para ese servicio.');
        }

        $booking = DB::transaction(function () use ($customer, $data, $source, $userId, $public, $start, $branch, $service, $candidates, $tenantId) {
            foreach ($candidates as $worker) {
                // Serializa a quienes compiten por el mismo profesional.
                $locked = Worker::whereKey($worker->id)->lockForUpdate()->first();
                if (!$locked || !$locked->is_active) {
                    continue;
                }

                [$work, $free] = Availability::freeIntervals($locked, $branch, $start->copy()->startOfDay());
                if (!Availability::fits($work, $free, $start, (int) $service->duration_minutes, (int) $service->buffer_minutes)) {
                    continue;
                }

                $status = $data['status'] ?? null;
                if (!$status || $public) {
                    $status = ($public && $service->requires_approval) ? 'pending' : 'confirmed';
                }
                if (!array_key_exists($status, Booking::statuses())) {
                    $status = 'confirmed';
                }

                $b = Booking::create([
                    'tenant_id'        => $tenantId,
                    'code'             => $this->uniqueCode(),
                    'manage_token'     => Str::random(40),
                    'branch_id'        => $branch->id,
                    'service_id'       => $service->id,
                    'worker_id'        => $locked->id,
                    'customer_id'      => $customer->id,
                    'starts_at'        => $start,
                    'ends_at'          => $start->copy()->addMinutes($service->duration_minutes),
                    'blocks_until'     => $start->copy()->addMinutes($service->duration_minutes + $service->buffer_minutes),
                    'status'           => $status,
                    'source'           => $source,
                    'service_name'     => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'price'            => $service->price,
                    'currency'         => OfficeSettings::forTenant($tenantId)->currency ?: 'BOB',
                    'worker_name'      => $locked->name,
                    'customer_notes'   => $data['customer_notes'] ?? null,
                    'internal_notes'   => $data['internal_notes'] ?? null,
                    'confirmed_at'     => $status === 'confirmed' ? now() : null,
                ]);

                $this->log($b, 'created', $public ? 'customer' : 'staff', $userId, ['status' => $status, 'starts_at' => $start->toDateTimeString(), 'worker' => $locked->name]);

                return $b;
            }

            throw new OfficeException('Ese horario acaba de ocuparse. Elige otro, por favor.');
        });

        Event::fire('aero.office.bookingCreated', [$booking]);
        if ($booking->status === 'confirmed') {
            Event::fire('aero.office.bookingConfirmed', [$booking]);
        }

        return $booking;
    }

    public function confirm(Booking $b, ?int $userId = null): Booking
    {
        $b = $this->transition($b, 'confirmed', 'confirmed', 'staff', $userId, null, ['confirmed_at' => now()]);
        Event::fire('aero.office.bookingConfirmed', [$b]);

        return $b;
    }

    public function reject(Booking $b, ?string $reason = null, ?int $userId = null): Booking
    {
        $b = $this->transition($b, 'rejected', 'rejected', 'staff', $userId, $reason, ['cancel_reason' => $reason]);
        Event::fire('aero.office.bookingRejected', [$b]);

        return $b;
    }

    public function cancel(Booking $b, ?string $reason = null, string $actor = 'staff', ?int $userId = null): Booking
    {
        if ($actor === 'customer') {
            $hours = (int) OfficeSettings::forTenant($b->tenant_id)->cancel_window_hours;
            if ($hours > 0 && now()->greaterThan($b->starts_at->copy()->subHours($hours))) {
                throw new OfficeException("Solo se puede cancelar hasta {$hours} h antes de la cita. Comunícate con el negocio.");
            }
        }
        $b = $this->transition($b, 'cancelled', 'cancelled', $actor, $userId, $reason, ['cancelled_at' => now(), 'cancel_reason' => $reason]);
        Event::fire('aero.office.bookingCancelled', [$b]);

        return $b;
    }

    public function startService(Booking $b, ?int $userId = null): Booking
    {
        return $this->transition($b, 'in_service', 'status', 'staff', $userId);
    }

    public function complete(Booking $b, ?int $userId = null): Booking
    {
        $b = $this->transition($b, 'completed', 'status', 'staff', $userId);
        Event::fire('aero.office.bookingCompleted', [$b]);

        return $b;
    }

    public function markNoShow(Booking $b, ?int $userId = null): Booking
    {
        return $this->transition($b, 'no_show', 'status', 'staff', $userId);
    }

    /** Mueve la cita (y opcionalmente cambia de profesional). Conserva historial. */
    public function reschedule(Booking $b, $newStart, ?int $workerId = null, string $actor = 'staff', ?int $userId = null): Booking
    {
        $start = Carbon::parse($newStart)->seconds(0);
        $public = $actor === 'customer';

        $b = DB::transaction(function () use ($b, $start, $workerId, $actor, $userId, $public) {
            $fresh = Booking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            if ($fresh->isFinal()) {
                throw new OfficeException('Esta reserva ya no se puede reprogramar.');
            }
            $service = Service::findOrFail($fresh->service_id);
            $branch = Branch::findOrFail($fresh->branch_id);

            if ($public) {
                $settings = OfficeSettings::forTenant($fresh->tenant_id);
                if ($start->lt(now()->addHours((int) $settings->min_notice_hours))) {
                    throw new OfficeException('Ese horario ya no admite reservas por falta de anticipación.');
                }
                $hours = (int) $settings->cancel_window_hours;
                if ($hours > 0 && now()->greaterThan($fresh->starts_at->copy()->subHours($hours))) {
                    throw new OfficeException("Solo se puede reprogramar hasta {$hours} h antes de la cita.");
                }
            }

            $worker = Worker::whereKey($workerId ?: $fresh->worker_id)->where('tenant_id', $fresh->tenant_id)->lockForUpdate()->first();
            if (!$worker || !$worker->is_active) {
                throw new OfficeException('El profesional ya no está disponible.');
            }
            if ($workerId && !Availability::eligibleWorkers($service, $branch)->contains('id', $worker->id)) {
                throw new OfficeException('Ese profesional no atiende este servicio en la sucursal.');
            }

            [$work, $free] = Availability::freeIntervals($worker, $branch, $start->copy()->startOfDay(), $fresh->id);
            if (!Availability::fits($work, $free, $start, (int) $fresh->duration_minutes, (int) $service->buffer_minutes)) {
                throw new OfficeException('Ese horario no está disponible.');
            }

            $old = ['starts_at' => $fresh->starts_at->toDateTimeString(), 'worker' => $fresh->worker_name];
            $fresh->starts_at = $start;
            $fresh->ends_at = $start->copy()->addMinutes($fresh->duration_minutes);
            $fresh->blocks_until = $start->copy()->addMinutes($fresh->duration_minutes + $service->buffer_minutes);
            $reassigned = $worker->id !== (int) $fresh->worker_id;
            $fresh->worker_id = $worker->id;
            $fresh->worker_name = $worker->name;
            // El cliente que mueve una cita que requiere aprobación vuelve a esperarla.
            if ($public && $service->requires_approval && $fresh->status === 'confirmed') {
                $fresh->status = 'pending';
                $fresh->confirmed_at = null;
            }
            $fresh->save();

            $this->log($fresh, $reassigned ? 'reassigned' : 'rescheduled', $actor, $userId,
                ['from' => $old, 'to' => ['starts_at' => $start->toDateTimeString(), 'worker' => $worker->name]]);

            return $fresh;
        });

        Event::fire('aero.office.bookingRescheduled', [$b]);

        return $b;
    }

    public function reassign(Booking $b, int $workerId, ?int $userId = null): Booking
    {
        return $this->reschedule($b, $b->starts_at, $workerId, 'staff', $userId);
    }

    public function updateNotes(Booking $b, ?string $internal, ?int $userId = null): Booking
    {
        $b->update(['internal_notes' => $internal]);
        $this->log($b, 'edited', 'staff', $userId, ['internal_notes' => true]);

        return $b;
    }

    /** Candidatos en orden: el pedido, o los elegibles ordenados por menos carga ese día. */
    protected function candidates(Service $service, Branch $branch, $workerId, bool $public, Carbon $start)
    {
        $eligible = Availability::eligibleWorkers($service, $branch, $public);
        if ($workerId) {
            return $eligible->where('id', (int) $workerId)->values();
        }
        if ($public && !(OfficeSettings::forTenant($service->tenant_id)->auto_assign_worker ?? true)) {
            throw new OfficeException('Elige un profesional para continuar.');
        }

        $load = Booking::where('tenant_id', $service->tenant_id)->blocking()
            ->whereDate('starts_at', $start->toDateString())
            ->whereIn('worker_id', $eligible->pluck('id'))
            ->selectRaw('worker_id, count(*) n')->groupBy('worker_id')->pluck('n', 'worker_id');

        return $eligible->sortBy(fn ($w) => [(int) ($load[$w->id] ?? 0), $w->id])->values();
    }

    protected function transition(Booking $b, string $to, string $action, string $actor, ?int $userId, ?string $note = null, array $extra = []): Booking
    {
        $fresh = DB::transaction(function () use ($b, $to, $action, $actor, $userId, $note, $extra) {
            $row = Booking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            if (!in_array($to, self::TRANSITIONS[$row->status] ?? [], true)) {
                throw new OfficeException('No se puede pasar de «' . $row->status_label . '» a «' . (Booking::statuses()[$to] ?? $to) . '».');
            }
            $from = $row->status;
            $row->fill($extra);
            $row->status = $to;
            $row->save();
            $this->log($row, $action, $actor, $userId, ['from' => $from, 'to' => $to], $note);

            return $row;
        });

        $b->setRawAttributes($fresh->getAttributes(), true);
        Event::fire('aero.office.bookingStatusChanged', [$b]);

        return $b;
    }

    protected function log(Booking $b, string $action, string $actor, ?int $userId, array $changes = [], ?string $note = null): void
    {
        BookingLog::create([
            'tenant_id'  => $b->tenant_id,
            'booking_id' => $b->id,
            'user_id'    => $userId,
            'actor'      => $actor,
            'action'     => $action,
            'changes'    => $changes ?: null,
            'note'       => $note ? mb_substr($note, 0, 250) : null,
            'created_at' => now(),
        ]);
    }

    protected function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
            $code = strtr($code, ['0' => 'K', 'O' => 'M', '1' => 'H', 'I' => 'N', 'L' => 'P']);
        } while (Booking::where('code', $code)->exists());

        return $code;
    }
}
