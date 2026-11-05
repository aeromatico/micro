<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use ApplicationException;
use Illuminate\Support\Facades\DB;

/**
 * Moderación de eventos por la plataforma. `moderation`: ok | pending (en revisión) | rejected | suspended.
 * Un evento que no está «ok» no se muestra, no vende y no permite el ingreso (ver Event::platformBlocked).
 */
class ModerationService
{
    public static bool $writing = false;

    protected static function admin(): array
    {
        $a = Audit::actor();
        if ($a['type'] !== 'admin') {
            throw new ApplicationException('Se necesita un administrador autenticado.');
        }

        return $a;
    }

    protected static function change(Event $event, string $to, array $from, ?string $note, bool $noteRequired, ?string $newStatus = null): Event
    {
        $a = self::admin();
        $note = $note !== null ? trim($note) : null;
        if ($noteRequired && mb_strlen((string) $note) < 5) {
            throw new ApplicationException('Escribe el motivo (se le informa al organizador y queda en el historial).');
        }

        return DB::transaction(function () use ($event, $to, $from, $note, $a, $newStatus) {
            $e = Event::lockForUpdate()->findOrFail($event->id);
            if (!in_array($e->moderation, $from, true)) {
                throw new ApplicationException('Este evento no admite esa acción en su estado actual.');
            }
            $prev = [$e->moderation, $e->status];
            self::$writing = true;
            try {
                $e->moderation = $to;
                $e->moderation_note = $note ? mb_substr($note, 0, 500) : null;
                $e->moderated_at = now();
                $e->moderated_by = $a['name'];
                if ($newStatus) {
                    $e->status = $newStatus;
                }
                $e->save();
            } finally {
                self::$writing = false;
            }
            Audit::record('event.moderation.' . $to, 'event', $e->id, ['tenant_id' => $e->tenant_id, 'event_id' => $e->id, 'changes' => ['moderation' => [$prev[0], $to]] + ($newStatus ? ['status' => [$prev[1], $newStatus]] : []), 'note' => $note]);
            \Event::fire('aero.tickets.event' . ucfirst($to), [$e]);
            $event->setRawAttributes($e->getAttributes(), true);

            return $e;
        });
    }

    protected static function notify(Event $e, string $code, ?string $note): void
    {
        [$email, $name] = OrganizerService::contact((int) $e->tenant_id);
        if ($email) {
            Templates::send($email, $name, $code, ['contact_name' => $name, 'event_name' => $e->name, 'note' => (string) $note]);
        }
    }

    public static function approve(Event $e, ?string $note = null): Event
    {
        return self::change($e, 'ok', ['pending', 'rejected'], $note, false);
    }

    /** Rechazo: vuelve a borrador para que el organizador lo corrija. */
    public static function reject(Event $e, string $note): Event
    {
        $r = self::change($e, 'rejected', ['pending'], $note, true, 'draft');
        self::notify($r, 'event_rejected', $note);

        return $r;
    }

    public static function suspend(Event $e, string $note): Event
    {
        $r = self::change($e, 'suspended', ['ok', 'pending'], $note, true);
        self::notify($r, 'event_suspended', $note);

        return $r;
    }

    public static function restore(Event $e, ?string $note = null): Event
    {
        return self::change($e, 'ok', ['suspended'], $note, false);
    }

    /**
     * Cancelación por la plataforma: el evento queda cancelado, las órdenes se anulan y los pagos recibidos
     * quedan para devolución (Event::cancel hace la cascada). Exige motivo.
     */
    public static function cancel(Event $event, string $note): Event
    {
        $a = self::admin();
        $note = trim($note);
        if (mb_strlen($note) < 5) {
            throw new ApplicationException('Escribe el motivo de la cancelación.');
        }
        $e = Event::findOrFail($event->id);
        if (in_array($e->status, ['cancelled', 'finished'], true)) {
            throw new ApplicationException('Este evento ya está ' . mb_strtolower(Event::$statuses[$e->status]) . '.');
        }
        $prev = $e->status;
        $e->cancel();
        Audit::record('event.cancelled_by_platform', 'event', $e->id, ['tenant_id' => $e->tenant_id, 'event_id' => $e->id, 'changes' => ['status' => [$prev, 'cancelled']], 'note' => $note]);
        self::notify($e, 'event_cancelled_platform', $note);
        $event->setRawAttributes($e->fresh()->getAttributes(), true);

        return $e;
    }
}
