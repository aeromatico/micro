<?php namespace Aero\Tickets\Models;

use Model;

/** Entrada emitida / asistente. El código es el que se lee en el ingreso. */
class Ticket extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Tickets\Classes\BelongsToEvent;

    public $table = 'aero_tickets_tickets';
    public $fillable = ['event_id', 'session_id', 'ticket_type_id', 'guest_id', 'holder_name', 'holder_email', 'holder_phone', 'holder_document', 'status', 'source', 'amount', 'base_amount', 'fee_amount', 'currency', 'order_code', 'order_id', 'expires_at', 'notes'];

    public $rules = [
        'holder_name'  => 'required|string|max:255',
        'holder_email' => 'nullable|email|max:255',
        'status'       => 'in:pending,reserved,valid,used,cancelled,expired',
    ];

    public $attributes = ['status' => 'valid', 'source' => 'manual', 'currency' => 'BOB'];
    public $dates = ['paid_at', 'checked_in_at', 'expires_at'];

    /** Solo TicketIssuer crea entradas: así ninguna (ni cortesía) esquiva aforo ni cupos. */
    public static bool $viaIssuer = false;

    public $belongsTo = [
        'event' => [Event::class, 'key' => 'event_id'],
        'ticket_type' => [TicketType::class, 'key' => 'ticket_type_id'],
        'guest' => [Guest::class, 'key' => 'guest_id'],
        'order' => [Order::class, 'key' => 'order_id'],
    ];

    public static array $statuses = ['pending' => 'Pendiente de aprobación/pago', 'reserved' => 'Reservada', 'valid' => 'Emitida', 'used' => 'Utilizada', 'cancelled' => 'Anulada', 'expired' => 'Reserva vencida'];

    /** Lo que ve el asistente: emitido / utilizado / anulado / vencido (o pendiente si aún no se emite). */
    public static array $states = ['issued' => 'Emitida', 'used' => 'Utilizada', 'cancelled' => 'Anulada', 'expired' => 'Vencida', 'pending' => 'Pendiente de pago'];
    public static array $sources = ['registration' => 'Inscripción pública', 'guest' => 'Invitación', 'manual' => 'Manual (organizador)'];

    public function getStatusOptions(): array { return self::$statuses; }
    public function getSourceOptions(): array { return self::$sources; }

    public function getTicketTypeIdOptions(): array
    {
        return TicketType::visible()->with('event')->get()->mapWithKeys(fn ($t) => [$t->id => ($t->event?->name ?? '?') . ' — ' . $t->name])->all();
    }

    /** Ocupan cupo: pendientes, válidas, usadas y reservas aún vigentes. Una reserva vencida libera su cupo al instante. */
    public function scopeHolding($q)
    {
        return $q->where(function ($w) {
            $w->whereIn('status', ['pending', 'valid', 'used'])
              ->orWhere(fn ($r) => $r->where('status', 'reserved')->where('expires_at', '>', now()));
        });
    }

    /** Reservas vencidas que aún figuran como reservadas (las cierra el cron). */
    public function scopeLapsed($q)
    {
        return $q->where('status', 'reserved')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '<=', now()));
    }

    /** Cierra las reservas vencidas (el cupo ya estaba libre; esto ordena el estado). Devuelve cuántas. */
    public static function expireLapsed(): int
    {
        $n = 0;
        static::lapsed()->get()->each(function (self $t) use (&$n) {
            $t->status = 'expired';
            $t->save();
            $n++;
            \Event::fire('aero.tickets.reservationExpired', [$t]);
        });

        return $n;
    }

    public function isReservationLive(): bool
    {
        return $this->status === 'reserved' && $this->expires_at && $this->expires_at->isFuture();
    }

    public function beforeCreate(): void
    {
        if (!self::$viaIssuer) {
            throw new \ApplicationException('Las entradas solo se emiten desde el emisor (respeta aforo y cupos).');
        }
    }

    public function beforeSave(): void
    {
        if ($this->exists) {
            if ($this->isDirty('event_id') || $this->isDirty('ticket_type_id')) {
                throw new \ApplicationException('No se puede cambiar el evento ni el tipo de una entrada emitida; anúlala y emite otra.');
            }
            if ($this->isDirty('status') && in_array($this->getOriginal('status'), ['cancelled', 'expired'], true)) {
                throw new \ApplicationException('Una entrada anulada o vencida no se reactiva; emite una nueva.');
            }
        }

        foreach (['session_id', 'ticket_type_id', 'guest_id'] as $f) {
            if ($this->{$f} === '') $this->{$f} = null;
        }
        foreach (['ticket_type_id' => TicketType::class, 'guest_id' => Guest::class] as $f => $class) {
            if ($this->{$f} && !$this->refBelongsToEvent($class, $this->{$f})) {
                throw new \ApplicationException('Referencia inválida para este evento.');
            }
        }
        if (empty($this->code)) {
            do {
                $this->code = self::newCode();
            } while (static::where('code', $this->code)->exists());
        }
        if (empty($this->public_token)) {
            $this->public_token = \Str::random(40);
        }
        // Número dentro de la orden (único en BD: una entrada nunca se emite dos veces con el mismo número).
        if ($this->order_id && !$this->seq) {
            $this->seq = (int) static::where('order_id', $this->order_id)->max('seq') + 1;
        }
    }

    /** Código de puerta: 10 caracteres sin ambiguos (0/O, 1/I), aleatorio criptográfico. */
    public static function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, 31)];
        }

        return $out;
    }

    /** Firma del QR: impide fabricar códigos válidos aunque se conozca el formato. No incluye datos personales. */
    public static function signature(string $code): string
    {
        $key = hash_hmac('sha256', 'aero.tickets.qr.v1', (string) config('app.key'));
        $mac = hash_hmac('sha256', strtoupper($code), $key, true);

        return substr(strtoupper(strtr(rtrim(base64_encode($mac), '='), '+/', 'AB')), 0, 8);
    }

    /** Contenido del QR: solo versión + código + firma. */
    public function getQrPayloadAttribute(): string
    {
        return 'AT1-' . $this->code . '-' . self::signature($this->code);
    }

    /**
     * Interpreta lo leído en puerta. Acepta el QR completo (con firma, que se verifica) o el código escrito a mano.
     * Devuelve el código, o null si el QR trae una firma incorrecta.
     */
    public static function parseScan(string $input): ?string
    {
        $in = strtoupper(trim($input));
        if (preg_match('/^AT1-([A-Z0-9]{10})-([A-Z0-9]{8})$/', $in, $m)) {
            return hash_equals(self::signature($m[1]), $m[2]) ? $m[1] : null;
        }
        if (str_starts_with($in, 'AT1-')) {
            return null;                                    // formato nuestro pero malformado
        }

        return preg_replace('/[^A-Z0-9]/', '', $in) ?: null;
    }

    /** Estado para el asistente. Una entrada emitida de un evento ya terminado figura como vencida. */
    public function getStateAttribute(): string
    {
        return match ($this->status) {
            'used' => 'used',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            'valid' => ($e = Event::withTrashed()->find($this->event_id)) && in_array($e->status, ['finished'], true) ? 'expired' : 'issued',
            default => 'pending',
        };
    }

    public function getStateLabelAttribute(): string
    {
        return self::$states[$this->state] ?? $this->state;
    }

    public function getPublicUrlAttribute(): string
    {
        return Event::withTrashed()->findOrFail($this->event_id)->baseUrl() . '/entrada/' . $this->public_token;
    }
}
