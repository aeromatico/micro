<?php namespace Aero\Tickets\Models;

use ApplicationException;
use Illuminate\Support\Facades\DB;
use Model;

/**
 * Invitado (individual, pareja, familia o grupo) con su enlace privado. party_size = 1 + acompañantes.
 * Revocar invalida el enlace y las entradas todavía no usadas.
 */
class Guest extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Tickets\Classes\BelongsToEvent;

    public $table = 'aero_tickets_guests';
    public $fillable = ['event_id', 'ticket_type_id', 'name', 'email', 'phone', 'companions_allowed', 'invitation_kind', 'notes'];

    public $rules = [
        'name'               => 'required|string|max:255',
        'email'              => 'nullable|email|max:255',
        'phone'              => 'nullable|string|max:30',
        'companions_allowed' => 'required|integer|min:0|max:49',
        'invitation_kind'    => 'required|in:single,couple,family,group',
    ];

    public $attributes = ['party_size' => 1, 'companions_allowed' => 0, 'invitation_kind' => 'single', 'rsvp' => 'pending', 'status' => 'active'];
    public $dates = ['invited_at', 'responded_at', 'revoked_at'];

    public $belongsTo = [
        'event' => [Event::class, 'key' => 'event_id'],
        'ticket_type' => [TicketType::class, 'key' => 'ticket_type_id'],
    ];
    public $hasMany = ['tickets' => [Ticket::class, 'key' => 'guest_id']];

    public static array $rsvps = ['pending' => 'Sin respuesta', 'confirmed' => 'Confirmó', 'declined' => 'No asistirá'];
    public static array $kinds = ['single' => 'Individual', 'couple' => 'Pareja', 'family' => 'Familia', 'group' => 'Grupo'];
    public static array $invitationStatuses = ['draft' => 'Por enviar', 'sent' => 'Enviada', 'confirmed' => 'Confirmada', 'declined' => 'Rechazada', 'revoked' => 'Revocada'];

    public function getRsvpOptions(): array { return self::$rsvps; }
    public function getInvitationKindOptions(): array { return self::$kinds; }

    public function getTicketTypeIdOptions(): array
    {
        return TicketType::visible()->with('event')->orderBy('event_id')->orderBy('sort_order')->get()
            ->mapWithKeys(fn ($t) => [$t->id => ($t->event?->name ?? '?') . ' — ' . $t->name . ' (' . (TicketType::$kinds[$t->kind] ?? '') . ')'])->all();
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function beforeDelete(): void
    {
        if ($this->tickets()->exists()) {
            throw new ApplicationException('Este invitado ya tiene entradas; revoca la invitación en lugar de eliminarla.');
        }
    }

    public function beforeSave(): void
    {
        if ($this->ticket_type_id === '') $this->ticket_type_id = null;
        if ($this->ticket_type_id && !$this->refBelongsToEvent(TicketType::class, $this->ticket_type_id)) {
            throw new ApplicationException('El tipo de entrada no pertenece a este evento.');
        }

        // El tipo de invitación fija los acompañantes (individual 0, pareja 1).
        $this->companions_allowed = match ($this->invitation_kind) {
            'single' => 0,
            'couple' => 1,
            default => max(1, (int) $this->companions_allowed),
        };
        $this->party_size = 1 + (int) $this->companions_allowed;

        $this->email = $this->email !== null && $this->email !== '' ? mb_strtolower(trim($this->email)) : null;
        $this->phone = $this->phone !== null && $this->phone !== '' ? trim($this->phone) : null;

        // Un mismo correo o WhatsApp no se invita dos veces al mismo evento (revocados no cuentan).
        $others = static::where('event_id', $this->event_id)->where('status', 'active')
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id));

        if ($this->email && (clone $others)->where('email', $this->email)->exists()) {
            throw new ApplicationException('Ya hay un invitado con ese correo en este evento.');
        }
        if (($digits = $this->phoneDigits()) && (clone $others)->whereNotNull('phone')->get(['id', 'phone'])->contains(fn ($g) => $g->phoneDigits() === $digits)) {
            throw new ApplicationException('Ya hay un invitado con ese WhatsApp en este evento.');
        }

        if ($this->exists && $this->isDirty('companions_allowed') && $this->rsvp === 'confirmed' && $this->party_size < $this->confirmed_count) {
            throw new ApplicationException("Ya confirmó {$this->confirmed_count} personas; no puedes dejar menos lugares.");
        }

        if (empty($this->token)) $this->token = \Str::random(32);
    }

    /* ---------- enlaces y WhatsApp ---------- */

    public function phoneDigits(): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $this->phone);
        if ($d === '') return null;
        if (strlen($d) === 8) $d = '591' . $d; // celular boliviano sin prefijo

        return $d;
    }

    /**
     * El evento SIEMPRE por id: en las listas del panel la columna relacional `event`
     * (relation + select) pisa el atributo `event` con el nombre (un string).
     */
    protected function eventRecord(): Event
    {
        return Event::withTrashed()->findOrFail($this->event_id);
    }

    public function getInviteUrlAttribute(): string
    {
        return $this->eventRecord()->baseUrl() . '/invitacion/' . $this->token;
    }

    public function getInviteTextAttribute(): string
    {
        $e = $this->eventRecord();
        $when = $e->starts_at?->translatedFormat('l d \d\e F, H:i');

        return "Hola {$this->name}, estás invitado/a a {$e->name}" . ($when ? " — {$when}" : '') . ($e->venue_name ? " en {$e->venue_name}" : '')
            . ".\nConfirma tu asistencia aquí: {$this->invite_url}";
    }

    /** wa.me al número del invitado (o selector de contacto si no tiene). */
    public function getWhatsappUrlAttribute(): string
    {
        return 'https://wa.me/' . ($this->phoneDigits() ?? '') . '?text=' . rawurlencode($this->invite_text);
    }

    public function getInvitationStatusAttribute(): string
    {
        if ($this->isRevoked()) return 'revoked';
        if ($this->rsvp === 'confirmed') return 'confirmed';
        if ($this->rsvp === 'declined') return 'declined';

        return $this->invited_at ? 'sent' : 'draft';
    }

    public function getInvitationStatusLabelAttribute(): string
    {
        return self::$invitationStatuses[$this->invitation_status];
    }

    /* ---------- revocar / restaurar ---------- */

    /**
     * Revoca la invitación: el enlace deja de servir y las entradas sin usar se anulan.
     * Las ya usadas se respetan (la persona ya ingresó). Si una entrada de pago ya estaba
     * cobrada se marca con reembolso pendiente. Devuelve cuántas entradas se anularon.
     */
    public function revoke(?string $reason = null): int
    {
        return DB::transaction(function () use ($reason) {
            $g = static::lockForUpdate()->findOrFail($this->id);
            if ($g->isRevoked()) {
                return 0;
            }

            $live = $g->tickets()->whereIn('status', ['pending', 'reserved', 'valid'])->get();
            // Sus órdenes se cancelan (salvo las que ya tienen un ingreso: esas se respetan).
            foreach (Order::where('guest_id', $g->id)->whereIn('status', ['pending_payment', 'verifying', 'incident', 'confirmed'])->get() as $o) {
                try {
                    $o->cancel('Invitación revocada');
                } catch (ApplicationException $x) {
                    // ya hubo ingreso: se deja la orden
                }
            }
            $live = $g->tickets()->whereIn('status', ['pending', 'reserved', 'valid'])->get()->merge($live)->unique('id');
            foreach ($live as $t) {
                $t->refund_pending = $t->status === 'valid' && (float) $t->amount > 0;
                $t->status = 'cancelled';
                $t->save();
            }

            $g->status = 'revoked';
            $g->revoked_at = now();
            $g->revoke_reason = $reason ? mb_substr($reason, 0, 255) : null;
            $g->confirmed_count = 0;
            $g->save();

            \Event::fire('aero.tickets.guestRevoked', [$g, $live->count()]);
            $this->setRawAttributes($g->getAttributes(), true);

            return $live->count();
        });
    }

    /** Reactiva la invitación (vuelve a «sin respuesta»); las entradas anuladas no resucitan. */
    public function restore(): void
    {
        $this->status = 'active';
        $this->revoked_at = null;
        $this->revoke_reason = null;
        $this->rsvp = 'pending';
        $this->responded_at = null;
        $this->save();
    }
}
