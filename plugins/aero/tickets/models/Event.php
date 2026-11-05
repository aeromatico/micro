<?php namespace Aero\Tickets\Models;

use ApplicationException;
use Aero\Tickets\Classes\Audit as AuditLog;
use Aero\Tickets\Classes\CurrentTenant;
use Aero\Tickets\Classes\OrganizerService;
use Event as Events;
use Model;

/**
 * Evento (público, privado o mixto). Vive en un tenant (organizador).
 * Publicar exige los datos mínimos para que un asistente lo entienda y lo encuentre.
 */
class Event extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'aero_tickets_events';

    public $fillable = [
        'tenant_id', 'category_id', 'name', 'slug', 'description', 'visibility', 'registration', 'buyer_extra_label', 'buyer_extra_required', 'status',
        'starts_at', 'ends_at', 'department', 'city', 'zone', 'venue_name', 'address', 'address_reference',
        'lat', 'lng', 'capacity', 'min_age', 'requirements', 'organizer_name', 'organizer_description',
        'contact_name', 'contact_phone', 'contact_whatsapp', 'contact_email',
    ];

    public $rules = [
        'name'        => 'required|string|max:255',
        'visibility'  => 'required|in:public,link',
        'registration' => 'required|in:open,approval',
        'status'      => 'required|in:draft,published,ongoing,finished,cancelled',
        'starts_at'   => 'nullable|date',
        'ends_at'     => 'nullable|date|after:starts_at',
        'capacity'    => 'nullable|integer|min:1|max:1000000',
        'min_age'     => 'nullable|integer|min:0|max:99',
        'lat'         => 'nullable|numeric|between:-90,90',
        'lng'         => 'nullable|numeric|between:-180,180',
        'contact_email' => 'nullable|email|max:255',
    ];

    public $attributes = ['visibility' => 'public', 'registration' => 'open', 'status' => 'draft'];

    public $dates = ['starts_at', 'ends_at', 'published_at'];

    public $belongsTo = ['category' => [Category::class, 'key' => 'category_id']];

    public $hasMany = [
        'ticket_types' => [TicketType::class, 'key' => 'event_id'],
        'orders'       => [Order::class, 'key' => 'event_id'],
        'tickets'      => [Ticket::class, 'key' => 'event_id'],
        'sessions'     => [Session::class, 'key' => 'event_id'],
    ];

    public $attachOne = ['cover' => \System\Models\File::class];
    public $attachMany = ['gallery' => \System\Models\File::class];

    public static array $visibilities = [
        'public' => 'Público (catálogo y página propia)',
        'link' => 'Privado por enlace (no aparece en el catálogo)',
    ];

    public static array $registrations = [
        'open' => 'Abierta: quien tiene acceso obtiene su entrada',
        'approval' => 'Restringida: el organizador aprueba cada inscripción',
    ];

    public static array $statuses = [
        'draft' => 'Borrador', 'published' => 'Publicado', 'ongoing' => 'En curso', 'finished' => 'Finalizado', 'cancelled' => 'Cancelado',
    ];

    public static array $departments = [
        'La Paz', 'Cochabamba', 'Santa Cruz', 'Oruro', 'Potosí', 'Chuquisaca', 'Tarija', 'Beni', 'Pando',
    ];

    public function getVisibilityOptions(): array { return self::$visibilities; }
    public function getStatusOptions(): array { return self::$statuses; }
    public function getRegistrationOptions(): array { return self::$registrations; }
    /** Departamentos activos de «Ubicaciones» (si aún no hay tabla/filas, la lista de fábrica). */
    public function getDepartmentOptions(): array
    {
        $names = [];
        try {
            $names = Location::departments()->pluck('name')->all();
        } catch (\Throwable $e) {
        }
        $names = $names ?: self::$departments;
        if ($this->department && !in_array($this->department, $names, true)) {
            $names[] = $this->department;          // un evento ya existente no pierde su valor
        }

        return array_combine($names, $names);
    }

    /** Ciudades del departamento elegido (campo dependiente). */
    public function getCityOptions($value = null, $formData = null): array
    {
        $dept = $formData->department ?? $this->department;
        $names = [];
        try {
            $names = $dept ? Location::citiesOf($dept)->pluck('name')->all() : [];
        } catch (\Throwable $e) {
        }
        if ($this->city && !in_array($this->city, $names, true)) {
            $names[] = $this->city;
        }

        return array_combine($names, $names);
    }

    public function getCategoryIdOptions(): array
    {
        return Category::active()->pluck('name', 'id')->all();
    }

    /** Nombre del organizador (espacio de trabajo) para listas globales. */
    public function getOrganizerNameLabelAttribute(): string
    {
        try {
            return class_exists(\Aero\Sites\Models\Tenant::class) ? (string) (\Aero\Sites\Models\Tenant::find($this->tenant_id)?->name ?: '#' . $this->tenant_id) : '#' . $this->tenant_id;
        } catch (\Throwable $e) {
            return '#' . $this->tenant_id;
        }
    }

    public function getModerationLabelAttribute(): string
    {
        return ['ok' => 'Aprobado', 'pending' => 'En revisión', 'rejected' => 'Rechazado', 'suspended' => 'Suspendido'][$this->moderation] ?? (string) $this->moderation;
    }

    public function getCityFilterOptions(): array
    {
        return static::whereNotNull('city')->distinct()->orderBy('city')->pluck('city', 'city')->all();
    }

    public function getTenantIdOptions(): array
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return [];
        }

        return \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
    }

    /* ---------- tenant ---------- */

    public function scopeForTenant($q, ?int $tenantId)
    {
        return $tenantId ? $q->where($this->getTable() . '.tenant_id', $tenantId) : $q->whereRaw('1 = 0');
    }

    /** Lo que ve el usuario del panel: todo (superadmin) o solo su tenant; sin tenant, nada. */
    public function scopeVisible($q)
    {
        return CurrentTenant::isAdmin() ? $q : $this->scopeForTenant($q, CurrentTenant::id());
    }

    /** Catálogo general: solo públicos, publicados o en curso. Los privados nunca aparecen. */
    public function scopeListedPublicly($q)
    {
        $q->whereIn('status', ['published', 'ongoing'])->where('visibility', 'public')->where('moderation', 'ok');
        if ($blocked = OrganizerService::blockedTenantIds()) {
            $q->whereNotIn($this->getTable() . '.tenant_id', $blocked);
        }

        return $q;
    }

    /* ---------- ciclo de vida ---------- */

    public function beforeCreate(): void
    {
        if (empty($this->tenant_id) && !CurrentTenant::isAdmin() && \BackendAuth::getUser()) {
            $this->tenant_id = CurrentTenant::id();
        }
        if (\BackendAuth::getUser() && !CurrentTenant::isAdmin() && empty($this->tenant_id)) {
            throw new ApplicationException('Tu usuario no está asociado a un espacio de trabajo.');
        }
    }

    /** moderation solo cambia por ModerationService o al publicar (nadie la edita desde un formulario). */
    public bool $moderationFromPublish = false;

    /** Historial de modificaciones relevantes del evento. */
    public function afterCreate(): void
    {
        AuditLog::record('event.created', 'event', $this->id, ['tenant_id' => $this->tenant_id, 'event_id' => $this->id, 'note' => $this->name]);
    }

    public function afterUpdate(): void
    {
        $tracked = ['name', 'description', 'starts_at', 'ends_at', 'venue_name', 'address', 'department', 'city', 'capacity', 'visibility', 'registration', 'status', 'moderation', 'min_age', 'organizer_name', 'contact_phone', 'contact_email', 'category_id'];
        $changes = AuditLog::diff($this->getOriginal(), $this->getAttributes(), $tracked);
        if ($changes) {
            $actor = \BackendAuth::getUser() ? AuditLog::actor() : ['type' => 'system', 'id' => null, 'name' => 'Sistema'];
            if ($actor['type'] === 'admin' && !CurrentTenant::isAdmin() && !\Aero\Tickets\Classes\Admin::can('events')) {
                $actor['type'] = 'organizer';
            }
            AuditLog::record('event.updated', 'event', $this->id, ['tenant_id' => $this->tenant_id, 'event_id' => $this->id, 'changes' => $changes, 'actor' => $actor]);
        }
    }

    public function beforeSave(): void
    {
        if ($this->exists && $this->isDirty('moderation') && !\Aero\Tickets\Classes\ModerationService::$writing && !$this->moderationFromPublish) {
            throw new ApplicationException('La moderación solo la cambia la administración de la plataforma.');
        }
        foreach (['category_id', 'tenant_id', 'capacity', 'min_age', 'lat', 'lng', 'starts_at', 'ends_at'] as $f) {
            if ($this->{$f} === '') {
                $this->{$f} = null;
            }
        }

        if ($this->exists && $this->capacity !== null && $this->isDirty('capacity')) {
            $committed = $this->holdingCount();
            if ($this->capacity < $committed) {
                throw new ApplicationException("No puedes bajar la capacidad a {$this->capacity}: hay {$committed} entradas emitidas, reservadas o pendientes.");
            }
        }
        $this->guardPlatformLimits();

        if (empty($this->slug)) {
            $this->slug = $this->uniqueSlug();
        }

        if ($this->visibility === 'link' && empty($this->access_token)) {
            $this->access_token = \Str::random(32);
        }
    }

    /** Ubicaciones y tope de capacidad definidos por la plataforma (solo al cambiarlos: lo existente no se invalida). */
    protected function guardPlatformLimits(): void
    {
        $max = Settings::maxCapacityPerEvent();
        if ($max > 0 && $this->capacity !== null && (!$this->exists || $this->isDirty('capacity')) && $this->capacity > $max) {
            throw new ApplicationException("La capacidad máxima permitida por evento es {$max}.");
        }
        try {
            if (Location::where('type', 'department')->exists()) {
                if ($this->department && (!$this->exists || $this->isDirty('department')) && !Location::departments()->where('name', $this->department)->exists()) {
                    throw new ApplicationException('Ese departamento no está disponible. Elige uno de la lista.');
                }
                if ($this->city && $this->department && (!$this->exists || $this->isDirty('city') || $this->isDirty('department')) && !Location::citiesOf($this->department)->where('name', $this->city)->exists()) {
                    throw new ApplicationException('Esa ciudad no está disponible para el departamento elegido. Pide a la plataforma que la agregue.');
                }
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // sin tabla de ubicaciones: no se valida
        }
    }

    protected function uniqueSlug(): string
    {
        $base = \Str::slug($this->name) ?: 'evento';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /** Qué falta para poder publicar (lista vacía = listo). */
    public function publishProblems(): array
    {
        $p = [];
        if (!$this->description) $p[] = 'la descripción';
        if (!$this->category_id) $p[] = 'la categoría';
        if (!$this->starts_at) $p[] = 'la fecha y hora de inicio';
        elseif ($this->starts_at->isPast()) $p[] = 'una fecha de inicio futura';
        if (!$this->venue_name) $p[] = 'el nombre del lugar';
        if (!$this->city) $p[] = 'la ciudad';
        if (!$this->cover) $p[] = 'la imagen de portada';
        if (!$this->organizer_name) $p[] = 'el organizador';
        if (!$this->ticket_types()->where('is_active', true)->exists()) $p[] = 'al menos un tipo de entrada activo';
        if (!$this->contact_phone && !$this->contact_whatsapp && !$this->contact_email) $p[] = 'un dato de contacto';

        return $p;
    }

    public function publish(): void
    {
        if ($this->status === 'cancelled') {
            throw new ApplicationException('Un evento cancelado no se puede publicar.');
        }
        if ($this->moderation === 'suspended') {
            throw new ApplicationException('Este evento está suspendido por la plataforma; comunícate con soporte.');
        }
        if ($block = OrganizerService::publishBlock($this->tenant_id ? (int) $this->tenant_id : null)) {
            throw new ApplicationException($block);
        }
        if ($problems = $this->publishProblems()) {
            throw new ApplicationException('Para publicar falta: ' . implode(', ', $problems) . '.');
        }
        $max = Settings::maxActiveEvents();
        if ($max > 0 && $this->status !== 'published' && static::where('tenant_id', $this->tenant_id)->whereIn('status', ['published', 'ongoing'])->where('id', '!=', $this->id)->count() >= $max) {
            throw new ApplicationException("Alcanzaste el máximo de {$max} eventos activos. Finaliza o cancela alguno para publicar otro.");
        }

        $this->moderationFromPublish = true;
        $this->status = 'published';
        // Con revisión previa, el evento queda «en revisión» (oculto y sin ventas) hasta que la plataforma lo apruebe.
        $this->moderation = Settings::requireEventReview() ? 'pending' : 'ok';
        $this->moderation_note = null;
        $this->moderated_at = null;
        $this->moderated_by = null;
        $this->published_at = $this->published_at ?: now();
        $this->save();
        $this->moderationFromPublish = false;

        Events::fire('aero.tickets.eventPublished', [$this]);
    }

    public function unpublish(): void
    {
        if ($this->status !== 'published') {
            throw new ApplicationException('Solo se puede despublicar un evento publicado que aún no empezó.');
        }
        $this->status = 'draft';
        $this->save();
    }

    public function cancel(): void
    {
        if (in_array($this->status, ['finished', 'cancelled'], true)) {
            throw new ApplicationException('Este evento ya está ' . mb_strtolower(self::$statuses[$this->status]) . '.');
        }
        $this->status = 'cancelled';
        $this->save();

        // Un evento cancelado deja de vender y se anulan sus órdenes: las sin pagar se liberan y las cobradas
        // quedan marcadas para devolución (lo hacen OrderService::cancel y PaymentService al escuchar la cancelación).
        foreach (Order::where('event_id', $this->id)->whereIn('status', array_merge(Order::LIVE, ['confirmed']))->get() as $order) {
            try {
                $order->cancel('Evento cancelado');
            } catch (ApplicationException $e) {
                // ya hubo ingresos con esa orden: se respetan las entradas usadas
            }
        }

        Events::fire('aero.tickets.eventCancelled', [$this]);
    }

    /* ---------- ventas y disponibilidad ---------- */

    /** Cancelado, borrador o terminado no aceptan compras ni reservas. */
    public function acceptsSales(): bool
    {
        return in_array($this->status, ['published', 'ongoing'], true) && $this->platformBlocked() === null;
    }

    /** Motivo por el que la plataforma bloquea este evento (visibilidad, ventas e ingreso); null = opera normal. */
    public function platformBlocked(): ?string
    {
        return match ($this->moderation) {
            'suspended' => 'Evento suspendido por la plataforma.',
            'pending' => 'Evento en revisión por la plataforma.',
            'rejected' => 'Evento rechazado en la revisión de la plataforma.',
            default => OrganizerService::operationBlock($this->tenant_id ? (int) $this->tenant_id : null),
        };
    }

    public function holdingCount(): int
    {
        return (int) $this->tickets()->holding()->count();
    }

    /** Cupos libres del evento (null = sin tope). */
    public function available(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->holdingCount());
    }

    public function checkedInCount(): int
    {
        return (int) $this->tickets()->where('status', 'used')->count();
    }

    /** Ventas confirmadas por moneda: [BOB => 120.0]. Solo entradas válidas o usadas con monto. */
    public function revenue(): array
    {
        return $this->tickets()->whereIn('status', ['valid', 'used'])->where('amount', '>', 0)
            ->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency')
            ->map(fn ($v) => (float) $v)->all();
    }

    /** Cobros del evento: aprobado, por verificar, reembolsos y alertas (se recalcula siempre: no hay contadores que desactualizar). */
    public function paymentStats(): array
    {
        $q = Payment::where('event_id', $this->id);
        $approved = (clone $q)->whereIn('status', ['approved'])->selectRaw('currency, SUM(amount_received) t')->groupBy('currency')->pluck('t', 'currency')->map(fn ($v) => (float) $v)->all();
        $refund = (clone $q)->where('status', 'refund_pending')->selectRaw('currency, SUM(amount_received) t')->groupBy('currency')->pluck('t', 'currency')->map(fn ($v) => (float) $v)->all();

        return [
            'approved' => $approved, 'refund_amount' => $refund,
            'to_verify' => (clone $q)->where('status', 'submitted')->count(),
            'refund_pending' => (clone $q)->where('status', 'refund_pending')->count(),
            'late' => (clone $q)->where('is_late', true)->count(),
            'suspicious' => (clone $q)->where('is_suspicious', true)->count(),
        ];
    }

    /* ---------- enlaces ---------- */

    /** https://{dominio del tenant}; sin tenant, la URL de la app. */
    public function baseUrl(): string
    {
        try {
            if ($this->tenant_id && class_exists(\Aero\Sites\Models\Tenant::class) && ($t = \Aero\Sites\Models\Tenant::find($this->tenant_id))) {
                return 'https://' . $t->primary_domain;
            }
        } catch (\Throwable $e) {
            // sin tabla de tenants (entorno parcial): se usa la URL de la app
        }

        return rtrim(url('/'), '/');
    }

    /** Enlace para compartir: el de la página (público) o el de la página con su token (privado por enlace). */
    public function getShareUrlAttribute(): ?string
    {
        return match ($this->visibility) {
            'public' => $this->baseUrl() . '/evento/' . $this->slug,
            'link'   => $this->baseUrl() . '/evento/' . $this->slug . '?t=' . $this->access_token,
            default  => null,
        };
    }

    public function getPrivateUrlAttribute(): ?string
    {
        return $this->share_url;
    }

    /* ---------- estados automáticos ---------- */

    /** Fin efectivo: ends_at o, si no hay, 12 h después del inicio. */
    public function effectiveEnd(): ?\Carbon\Carbon
    {
        return $this->ends_at ?: $this->starts_at?->copy()->addHours(12);
    }

    /** Publicado → En curso → Finalizado según la hora. Devuelve cuántos cambió. */
    public static function syncStatuses(): int
    {
        $n = 0;
        static::whereIn('status', ['published', 'ongoing'])->whereNotNull('starts_at')->get()->each(function (self $e) use (&$n) {
            $new = $e->status;
            if ($e->effectiveEnd()->isPast()) {
                $new = 'finished';
            } elseif ($e->starts_at->isPast()) {
                $new = 'ongoing';
            }
            if ($new !== $e->status) {
                $e->status = $new;
                $e->save();
                $n++;
            }
        });

        return $n;
    }
}
