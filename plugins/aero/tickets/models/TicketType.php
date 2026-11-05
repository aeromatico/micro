<?php namespace Aero\Tickets\Models;

use Model;

/** Tipo de entrada: gratuita, pagada o de cortesía, con cupo y ventana de venta propios. */
class TicketType extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Tickets\Classes\BelongsToEvent;

    public $table = 'aero_tickets_ticket_types';
    public $fillable = ['event_id', 'session_id', 'name', 'description', 'kind', 'price', 'currency', 'fee_percent', 'fee_fixed', 'fee_payer', 'quantity', 'sales_start', 'sales_end', 'max_per_order', 'is_active', 'sort_order'];

    public $rules = [
        'name'          => 'required|string|max:255',
        'kind'          => 'required|in:free,paid,courtesy',
        'price'         => 'nullable|numeric|min:0|max:99999999',
        'currency'      => 'in:BOB',
        'fee_percent'   => 'nullable|numeric|min:0|max:50',
        'fee_fixed'     => 'nullable|numeric|min:0|max:100000',
        'fee_payer'     => 'nullable|in:buyer,organizer',
        'quantity'      => 'nullable|integer|min:1',
        'sales_end'     => 'nullable|date|after:sales_start',
        'max_per_order' => 'nullable|integer|min:1|max:50',
    ];

    public $attributes = ['kind' => 'free', 'currency' => 'BOB', 'is_active' => true, 'max_per_order' => 10];
    public $dates = ['sales_start', 'sales_end'];

    public $belongsTo = ['event' => [Event::class, 'key' => 'event_id']];
    public $hasMany = ['tickets' => [Ticket::class, 'key' => 'ticket_type_id']];

    public static array $kinds = ['free' => 'Gratuita', 'paid' => 'De pago', 'courtesy' => 'Cortesía'];

    public function getKindOptions(): array { return ['free' => 'Gratuita (no se cobra)', 'paid' => 'De pago', 'courtesy' => 'Cortesía (la entregas tú; no se vende en línea)']; }
    public function getCurrencyOptions(): array { return \Aero\Tickets\Classes\Currency::options(); }
    public function getFeePayerOptions(): array { return ['buyer' => 'El comprador (se suma al precio)', 'organizer' => 'El organizador (se descuenta)']; }

    /** Columna de lista: «12 emitidas · 3 reservadas · 5 disponibles». */
    public function getStockSummaryAttribute(): string
    {
        if (!$this->exists) return '';
        $i = \Aero\Tickets\Classes\Inventory::type($this);

        return $i['sold_out'] ? 'AGOTADA (' . $i['issued'] . ' emitidas)' : "{$i['issued']} emitidas · " . ($i['available'] === null ? 'sin tope' : $i['available'] . ' disponibles');
    }

    public function getPriceSummaryAttribute(): string
    {
        $q = \Aero\Tickets\Classes\Pricing::quote($this);
        if ($this->kind !== 'paid') return self::$kinds[$this->kind] ?? '';

        return \Aero\Tickets\Classes\Currency::format($q['total'], $q['currency']) . ($q['fee'] > 0 ? ' (base ' . number_format($q['base'], 2) . ' + comisión ' . number_format($q['fee'], 2) . ')' : '');
    }

    public function beforeSave(): void
    {
        $max = Settings::maxTicketTypesPerEvent();
        if (!$this->exists && $max > 0 && static::where('event_id', $this->event_id)->count() >= $max) {
            throw new \ApplicationException("Cada evento admite hasta {$max} tipos de entrada.");
        }
        foreach (['session_id', 'quantity', 'sales_start', 'sales_end', 'max_per_order'] as $f) {
            if ($this->{$f} === '') $this->{$f} = null;
        }
        if ($this->exists && $this->quantity !== null && $this->isDirty('quantity')) {
            $committed = $this->holdingCount();
            if ($this->quantity < $committed) {
                throw new \ApplicationException("No puedes bajar la cantidad a {$this->quantity}: ya hay {$committed} entradas comprometidas de este tipo.");
            }
        }
        foreach (['fee_percent', 'fee_fixed'] as $f) {
            if ($this->{$f} === '') $this->{$f} = null;
        }
        if ($this->fee_payer === '') $this->fee_payer = null;

        if ($this->kind !== 'paid') {
            $this->price = 0;
            $this->fee_percent = $this->fee_fixed = null;
        } elseif ((float) $this->price <= 0) {
            throw new \ApplicationException('Una entrada de pago necesita un precio mayor a 0.');
        }
    }

    /** Entradas que ocupan cupo (pendientes, válidas, usadas). */
    public function holdingCount(): int
    {
        return (int) Ticket::holding()->where('ticket_type_id', $this->id)->count();
    }

    public function available(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->holdingCount());
    }

    public function isOnSale(): bool
    {
        return $this->is_active
            && (!$this->sales_start || $this->sales_start->isPast())
            && (!$this->sales_end || $this->sales_end->isFuture());
    }
}
