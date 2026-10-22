<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Contract;
use Aero\Realestate\Models\ContractDocument;
use Aero\Realestate\Models\Agreement;
use Aero\Realestate\Models\Change;
use Aero\Realestate\Models\Document;
use Aero\Realestate\Models\Favorite;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\SavedSearch;
use Aero\Realestate\Models\RealestateSettings;
use Aero\Realestate\Models\Visit;

/**
 * Único punto por el que la Inmobiliaria avisa: dispara eventos realestate.* en Aero.Notify
 * (WhatsApp vía Hello, correo, panel). Integración blanda y NUNCA lanza: un aviso fallido
 * no puede romper un contrato.
 */
class Notifier
{
    /** Umbrales de días antes del vencimiento; 0 = vence hoy o ya venció. */
    public const THRESHOLDS = [60, 30, 7, 0];

    /** Reaviso de documentos pendientes, en días. */
    public const DOCS_EVERY_DAYS = 7;

    /** Los sembradores/demos lo activan para no escribir a teléfonos de prueba. */
    public static bool $muted = false;

    /** Interruptor de Configuración para todo aviso dirigido a la administración del tenant. */
    protected static function adminOn(?int $tenantId): bool
    {
        return RealestateSettings::forTenant($tenantId)->notify_admins;
    }

    public static function available(): bool
    {
        return class_exists(\Aero\Notify\Classes\Notify::class);
    }

    /** Recorre los contratos vigentes y avisa los que cruzaron un umbral nuevo. Devuelve cuántos contratos avisó. */
    public static function sendExpiryAlerts(): int
    {
        if (self::$muted) {
            return 0;
        }

        $count = 0;
        Contract::active()->whereNotNull('end_date')->whereDate('end_date', '<=', now()->addDays(max(self::THRESHOLDS))->toDateString())
            ->with(['property', 'owner', 'client', 'agent'])->orderBy('end_date')->chunkById(100, function ($contracts) use (&$count) {
                foreach ($contracts as $c) {
                    $threshold = self::thresholdFor($c);
                    if ($threshold === null) {
                        continue;
                    }
                    // Sin Notify no se marca como avisado: cuando exista se enviará.
                    if (self::fireExpiry($c, $threshold)) {
                        Contract::whereKey($c->id)->update(['last_alert_days' => $threshold]);
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** El umbral más cercano ya cruzado que aún no se avisó (null = nada que avisar). */
    public static function thresholdFor(Contract $c): ?int
    {
        $left = $c->days_left;
        if ($left === null) {
            return null;
        }

        $crossed = array_filter(self::THRESHOLDS, fn ($t) => $left <= $t);
        if (!$crossed) {
            return null;
        }
        $threshold = min($crossed);

        return ($c->last_alert_days === null || $threshold < $c->last_alert_days) ? $threshold : null;
    }

    public static function fireExpiry(Contract $c, int $threshold): bool
    {
        if (!self::available()) {
            return false;
        }

        try {
            $ctx = self::context($c);
            $base = ['tenant_id' => (int) $c->tenant_id];

            $people = [
                'anticresista' => $c->client,
                'propietario'  => $c->owner,
                'agente'       => $c->agent,
            ];
            $settings = RealestateSettings::forTenant($c->tenant_id);
            foreach ($people as $role => $person) {
                // anticresista y propietario son clientes de la inmobiliaria; el agente, su equipo
                if (!$person || (!$person->phone && !$person->email) || !($role === 'agente' ? $settings->notify_agents : $settings->notify_clients)) {
                    continue;
                }
                \Aero\Notify\Classes\Notify::fire('realestate.anticretico.expiring', $ctx + ['recipient_name' => $person->name, 'recipient_role' => $role], $base + [
                    'actor'     => ['name' => $person->name, 'email' => $person->email, 'phone' => $person->phone],
                    'dedup_key' => "realestate:{$c->id}:expiring:{$threshold}:{$role}:" . $c->end_date->toDateString(),
                ]);
            }

            self::adminOn($c->tenant_id) && \Aero\Notify\Classes\Notify::fire('realestate.anticretico.expiring_admin', $ctx, $base + [
                'dedup_key' => "realestate:{$c->id}:expiring_admin:{$threshold}:" . $c->end_date->toDateString(),
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::error("Aero.Realestate: no se pudo avisar el vencimiento del contrato {$c->id}: " . $e->getMessage());

            return false;
        }
    }

    /** Contratos vigentes hace más de una semana sin escritura/Derechos Reales verificados: aviso interno periódico. */
    public static function sendPendingDocuments(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $service = app(AnticreticoService::class);
        $count = 0;
        Contract::active()->where('activated_at', '<=', now()->subDays(self::DOCS_EVERY_DAYS))
            ->where(fn ($q) => $q->whereNull('docs_alert_at')->orWhere('docs_alert_at', '<=', now()->subDays(self::DOCS_EVERY_DAYS)))
            ->with(['property', 'owner', 'client', 'agent'])->chunkById(100, function ($contracts) use ($service, &$count) {
                foreach ($contracts as $c) {
                    $missing = $service->missingDocuments($c);
                    if (!$missing || !self::adminOn($c->tenant_id)) {
                        continue;
                    }
                    try {
                        $names = implode(', ', array_map(fn ($t) => ContractDocument::$types[$t] ?? $t, $missing));
                        \Aero\Notify\Classes\Notify::fire('realestate.anticretico.documents_pending', self::context($c) + ['missing' => $names], [
                            'tenant_id' => (int) $c->tenant_id,
                            'dedup_key' => "realestate:{$c->id}:docs:" . now()->format('oW'),
                        ]);
                        Contract::whereKey($c->id)->update(['docs_alert_at' => now()]);
                        $count++;
                    } catch (\Throwable $e) {
                        \Log::error("Aero.Realestate: no se pudo avisar documentos del contrato {$c->id}: " . $e->getMessage());
                    }
                }
            });

        return $count;
    }

    // ───────────────────────── Visitas y seguimiento comercial ─────────────────────────

    /**
     * Avisa de una visita: a la inmobiliaria si fue solicitada desde el portal; al cliente y al agente en el resto.
     * Respeta los interruptores de Configuración. Nunca lanza.
     */
    public static function fireVisit(string $kind, Visit $v, array $extra = []): bool
    {
        if (self::$muted || !self::available()) {
            return false;
        }

        try {
            $v->loadMissing(['property', 'client', 'agent']);
            $settings = RealestateSettings::forTenant($v->tenant_id);
            $ctx = self::visitContext($v, $settings) + $extra;
            $base = ['tenant_id' => (int) $v->tenant_id];
            $stamp = $v->starts_at->timestamp . ':' . $v->status;

            if ($kind === 'requested') {
                if (!self::adminOn($v->tenant_id)) {
                    return false;
                }
                \Aero\Notify\Classes\Notify::fire('realestate.visit.requested', $ctx, $base + ['dedup_key' => "realestate:visit:{$v->id}:requested"]);

                return true;
            }

            $people = [];
            if ($settings->notify_clients && $kind !== 'cancelled_by_client') {
                $people['cliente'] = $v->client;
            }
            if ($settings->notify_agents) {
                $people['agente'] = $v->agent;
            }
            foreach ($people as $role => $person) {
                if (!$person || (!$person->phone && !$person->email)) {
                    continue;
                }
                \Aero\Notify\Classes\Notify::fire('realestate.visit.' . $kind, $ctx + ['recipient_name' => $person->name, 'recipient_role' => $role], $base + [
                    'actor'     => ['name' => $person->name, 'email' => $person->email, 'phone' => $person->phone],
                    'dedup_key' => "realestate:visit:{$v->id}:{$kind}:{$role}:{$stamp}",
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            \Log::error("Aero.Realestate: no se pudo avisar la visita {$v->id} ({$kind}): " . $e->getMessage());

            return false;
        }
    }

    public static function visitContext(Visit $v, ?RealestateSettings $settings = null): array
    {
        $settings ??= RealestateSettings::forTenant($v->tenant_id);
        $tenant = self::tenant($v->tenant_id);
        $p = $v->property;

        return [
            'code'             => $v->code,
            'client_name'      => (string) ($v->client?->name ?? ''),
            'client_phone'     => (string) ($v->client?->phone ?? ''),
            'agent_name'       => (string) ($v->agent?->name ?? ''),
            'agent_phone'      => (string) ($v->agent?->whatsapp ?: $v->agent?->phone ?? ''),
            'property_title'   => (string) ($p?->title ?? ''),
            'property_address' => (string) ($p?->address ?? ''),
            'map_url'          => ($p && $p->lat && $p->lng) ? 'https://maps.google.com/?q=' . $p->lat . ',' . $p->lng : '',
            'starts_at'        => $v->starts_at->locale('es')->translatedFormat('l d/m \a \l\a\s H:i'),
            'business_name'    => (string) ($settings->business_name ?: $tenant?->name),
            'tenant_name'      => $tenant?->name,
            'reason'           => (string) ($v->cancel_reason ?? ''),
            'url'              => \Backend::url('aero/realestate/visits/update/' . $v->id),
        ];
    }

    /** Recordatorios de visita: confirmadas dentro de la ventana de cada inmobiliaria, una sola vez por horario. */
    public static function sendVisitReminders(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $sent = 0;
        $tenantIds = Visit::whereIn('status', ['confirmed', 'rescheduled'])->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDays(7))->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tid) {
            $hours = RealestateSettings::forTenant((int) $tid)->reminder_hours;
            if ($hours <= 0) {
                continue;
            }
            Visit::where('tenant_id', $tid)->whereIn('status', ['confirmed', 'rescheduled'])->whereNull('reminder_sent_at')
                ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours($hours))
                ->with(['property', 'client', 'agent'])->orderBy('starts_at')->limit(200)->get()
                ->each(function (Visit $v) use ($hours, &$sent) {
                    // Agendada dentro de la ventana: la confirmación ya fue el aviso.
                    if ($v->created_at && $v->created_at->gt($v->starts_at->copy()->subHours($hours))) {
                        Visit::whereKey($v->id)->update(['reminder_sent_at' => now()]);

                        return;
                    }
                    if (self::fireVisit('reminder', $v)) {
                        Visit::whereKey($v->id)->update(['reminder_sent_at' => now()]);
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /** Seguimientos comerciales vencidos: aviso al agente (o a la inmobiliaria si no tiene agente), una vez por fecha. */
    public static function sendFollowUps(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $sent = 0;
        Lead::open()->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('follow_up_alerted_at')->orWhereColumn('follow_up_alerted_at', '<', 'next_follow_up_at'))
            ->with(['client', 'property', 'agent'])->chunkById(100, function ($leads) use (&$sent) {
                foreach ($leads as $l) {
                    try {
                        $tenant = self::tenant($l->tenant_id);
                        $ctx = [
                            'client_name' => (string) $l->client?->name, 'client_phone' => (string) $l->client?->phone,
                            'property_title' => (string) ($l->property?->title ?? 'sin propiedad'), 'stage' => $l->stage_name,
                            'due' => $l->next_follow_up_at->format('d/m/Y H:i'), 'agent_name' => (string) ($l->agent?->name ?? ''),
                            'tenant_name' => $tenant?->name, 'url' => \Backend::url('aero/realestate/leads/update/' . $l->id),
                        ];
                        $key = "realestate:lead:{$l->id}:followup:" . $l->next_follow_up_at->timestamp;
                        $agent = $l->agent;
                        $settings = RealestateSettings::forTenant($l->tenant_id);
                        if ($agent && !$settings->notify_agents) {
                            // con agente y avisos a agentes apagados: no se avisa a nadie (tampoco a la administración)
                        } elseif ($agent && ($agent->phone || $agent->email)) {
                            \Aero\Notify\Classes\Notify::fire('realestate.lead.follow_up', $ctx + ['recipient_name' => $agent->name], [
                                'tenant_id' => (int) $l->tenant_id, 'dedup_key' => $key,
                                'actor' => ['name' => $agent->name, 'email' => $agent->email, 'phone' => $agent->phone],
                            ]);
                        } elseif ($settings->notify_admins) {
                            \Aero\Notify\Classes\Notify::fire('realestate.lead.follow_up_unassigned', $ctx, ['tenant_id' => (int) $l->tenant_id, 'dedup_key' => $key]);
                        }
                        Lead::whereKey($l->id)->update(['follow_up_alerted_at' => now()]);
                        $sent++;
                    } catch (\Throwable $e) {
                        \Log::error("Aero.Realestate: no se pudo avisar el seguimiento del lead {$l->id}: " . $e->getMessage());
                    }
                }
            });

        return $sent;
    }

    // ───────────────────────── Portal: consultas y alertas ─────────────────────────

    /** Consulta recibida desde el portal: a la inmobiliaria y al agente responsable. */
    public static function fireInquiry(Lead $lead, string $message = '', $visit = null): bool
    {
        if (self::$muted || !self::available()) {
            return false;
        }

        try {
            $lead->loadMissing(['client', 'property', 'agent']);
            $tenant = self::tenant($lead->tenant_id);
            $ctx = [
                'client_name' => (string) $lead->client?->name, 'client_phone' => (string) $lead->client?->phone, 'client_email' => (string) $lead->client?->email,
                'property_title' => (string) ($lead->property?->title ?? ''), 'message' => $message,
                'visit_at' => $visit ? $visit->starts_at->locale('es')->translatedFormat('l d/m \\a \\l\\a\\s H:i') : '',
                'agent_name' => (string) ($lead->agent?->name ?? ''), 'tenant_name' => $tenant?->name,
                'url' => \Backend::url('aero/realestate/leads/update/' . $lead->id),
            ];
            $base = ['tenant_id' => (int) $lead->tenant_id];
            self::adminOn($lead->tenant_id) && \Aero\Notify\Classes\Notify::fire('realestate.inquiry.received', $ctx, $base + ['dedup_key' => "realestate:lead:{$lead->id}:inquiry:" . now()->format('YmdHi')]);

            $agent = $lead->agent;
            if ($agent && ($agent->phone || $agent->email) && RealestateSettings::forTenant($lead->tenant_id)->notify_agents) {
                \Aero\Notify\Classes\Notify::fire('realestate.inquiry.assigned', $ctx + ['recipient_name' => $agent->name], $base + [
                    'actor'     => ['name' => $agent->name, 'email' => $agent->email, 'phone' => $agent->phone],
                    'dedup_key' => "realestate:lead:{$lead->id}:inquiry_agent:" . now()->format('YmdHi'),
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            \Log::error("Aero.Realestate: no se pudo avisar la consulta del lead {$lead->id}: " . $e->getMessage());

            return false;
        }
    }

    public static function portalUrl(?int $tenantId, string $path = '/inmuebles'): string
    {
        $tenant = self::tenant($tenantId);

        return $tenant?->primary_domain ? 'https://' . $tenant->primary_domain . $path : '';
    }

    /** Búsquedas guardadas: avisa lo publicado desde el último aviso que cumple los criterios. */
    public static function sendSearchAlerts(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $sent = 0;
        SavedSearch::where('is_active', true)->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone'))
            ->where(fn ($q) => $q->whereNull('last_alert_at')->orWhere('last_alert_at', '<=', now()->subHour()))
            ->chunkById(100, function ($searches) use (&$sent) {
                foreach ($searches as $s) {
                    try {
                        $since = $s->last_alert_at ?? $s->created_at;
                        $found = \Aero\Realestate\Classes\PortalSearch::query((int) $s->tenant_id, (array) $s->criteria)
                            ->where('published_at', '>', $since)->reorder()->orderByDesc('published_at')->limit(10)->get();
                        if ($found->isEmpty()) {
                            continue;
                        }
                        $tenant = self::tenant($s->tenant_id);
                        $base = self::portalUrl($s->tenant_id, '');
                        $list = $found->map(fn ($p) => '• ' . $p->title . ' — ' . ($p->offers->first()?->label ?? '') . ($base ? ' ' . $base . '/inmueble/' . $p->slug : ''))->implode("\n");
                        \Aero\Notify\Classes\Notify::fire('realestate.alert.new_properties', [
                            'search_name' => $s->name, 'count' => (string) $found->count(), 'list' => $list, 'recipient_name' => '',
                            'business_name' => (string) $tenant?->name, 'tenant_name' => $tenant?->name,
                            'unsubscribe_url' => $base ? $base . '/inmuebles/alerta/' . $s->token : '',
                        ], [
                            'tenant_id' => (int) $s->tenant_id, 'actor' => ['name' => '', 'email' => $s->email, 'phone' => $s->phone],
                            'dedup_key' => "realestate:search:{$s->id}:" . $found->max('published_at')->timestamp,
                        ]);
                        $sent++;
                    } catch (\Throwable $e) {
                        \Log::error("Aero.Realestate: no se pudo avisar la búsqueda {$s->id}: " . $e->getMessage());
                    } finally {
                        SavedSearch::whereKey($s->id)->update(['last_alert_at' => now()]);
                    }
                }
            });

        return $sent;
    }

    /** Cambios de precio y de disponibilidad (con 10 minutos de calma por si se sigue editando): a quienes guardaron la propiedad. */
    public static function sendChangeAlerts(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $sent = 0;
        Change::whereNull('notified_at')->where('created_at', '<=', now()->subMinutes(10))->with('property')->orderBy('id')->limit(300)->get()
            ->each(function (Change $c) use (&$sent) {
                try {
                    $p = $c->property;
                    $tenant = self::tenant($c->tenant_id);
                    $users = $p && $p->is_published ? Favorite::where('property_id', $c->property_id)->pluck('user_id')->all() : [];
                    $url = $p ? self::portalUrl($c->tenant_id, '/inmueble/' . $p->slug) : '';
                    $names = \Aero\Realestate\Models\Property::$statuses;

                    foreach (\RainLab\User\Models\User::whereIn('id', $users)->get() as $u) {
                        $phone = \Aero\Realestate\Models\Client::where('tenant_id', $c->tenant_id)->where('user_id', $u->id)->value('phone');
                        $ctx = [
                            'recipient_name' => $u->first_name, 'property_title' => (string) $p->title, 'url' => $url,
                            'business_name' => (string) $tenant?->name, 'tenant_name' => $tenant?->name,
                            'old_value' => $c->kind === 'price' ? number_format((float) $c->old_value, 0, ',', '.') . ' ' . $c->currency : ($names[$c->old_value] ?? (string) $c->old_value),
                            'new_value' => $c->kind === 'price' ? number_format((float) $c->new_value, 0, ',', '.') . ' ' . $c->currency : ($names[$c->new_value] ?? (string) $c->new_value),
                        ];
                        \Aero\Notify\Classes\Notify::fire($c->kind === 'price' ? 'realestate.alert.price_changed' : 'realestate.alert.availability_changed', $ctx, [
                            'tenant_id' => (int) $c->tenant_id, 'actor' => ['name' => $u->first_name, 'email' => $u->email, 'phone' => $phone],
                            'dedup_key' => "realestate:change:{$c->id}:{$u->id}",
                        ]);
                        $sent++;
                    }
                } catch (\Throwable $e) {
                    \Log::error("Aero.Realestate: no se pudo avisar el cambio {$c->id}: " . $e->getMessage());
                } finally {
                    $c->forceFill(['notified_at' => now()])->save();
                }
            });

        return $sent;
    }

    // ───────────────────────── Contratos de alquiler y archivo documental ─────────────────────────

    /** Alquileres firmados que cruzaron un umbral de vencimiento: inquilino, propietario, agente y administración. */
    public static function sendRentalExpiryAlerts(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $count = 0;
        Agreement::expiring(max(self::THRESHOLDS))->with(['property', 'owner', 'client', 'agent'])->orderBy('end_date')->chunkById(100, function ($agreements) use (&$count) {
            foreach ($agreements as $a) {
                $left = $a->days_left;
                $crossed = array_filter(self::THRESHOLDS, fn ($t) => $left <= $t);
                if (!$crossed) {
                    continue;
                }
                $threshold = min($crossed);
                if ($a->last_alert_days !== null && $threshold >= $a->last_alert_days) {
                    continue;
                }
                if (self::fireRentalExpiry($a, $threshold)) {
                    Agreement::whereKey($a->id)->update(['last_alert_days' => $threshold]);
                    $count++;
                }
            }
        });

        return $count;
    }

    public static function fireRentalExpiry(Agreement $a, int $threshold): bool
    {
        try {
            $tenant = self::tenant($a->tenant_id);
            $ctx = [
                'code' => $a->code, 'property_title' => (string) ($a->property?->title ?? ''), 'property_address' => (string) ($a->property?->address ?? ''),
                'amount' => number_format((float) $a->amount, 2, ',', '.') . ' ' . $a->currency, 'end_date' => $a->end_date->format('d/m/Y'),
                'days_left' => (string) max(0, (int) $a->days_left), 'owner_name' => (string) ($a->owner?->name ?? ''), 'client_name' => (string) ($a->client?->name ?? ''),
                'agent_name' => (string) ($a->agent?->name ?? ''), 'business_name' => (string) ($tenant?->name ?? ''), 'tenant_name' => $tenant?->name,
                'url' => \Backend::url('aero/realestate/agreements/update/' . $a->id),
            ];
            $base = ['tenant_id' => (int) $a->tenant_id];

            $settings = RealestateSettings::forTenant($a->tenant_id);
            foreach (['inquilino' => $a->client, 'propietario' => $a->owner, 'agente' => $a->agent] as $role => $person) {
                if (!$person || (!$person->phone && !$person->email) || !($role === 'agente' ? $settings->notify_agents : $settings->notify_clients)) {
                    continue;
                }
                \Aero\Notify\Classes\Notify::fire('realestate.rental.expiring', $ctx + ['recipient_name' => $person->name, 'recipient_role' => $role], $base + [
                    'actor'     => ['name' => $person->name, 'email' => $person->email, 'phone' => $person->phone],
                    'dedup_key' => "realestate:agreement:{$a->id}:expiring:{$threshold}:{$role}:" . $a->end_date->toDateString(),
                ]);
            }
            self::adminOn($a->tenant_id) && \Aero\Notify\Classes\Notify::fire('realestate.rental.expiring_admin', $ctx, $base + ['dedup_key' => "realestate:agreement:{$a->id}:expiring_admin:{$threshold}:" . $a->end_date->toDateString()]);

            return true;
        } catch (\Throwable $e) {
            \Log::error("Aero.Realestate: no se pudo avisar el vencimiento del contrato {$a->id}: " . $e->getMessage());

            return false;
        }
    }

    /** Documentos del archivo: resumen semanal de los pendientes de revisión y aviso de los que vencen en 30 días. */
    public static function sendDocumentAlerts(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $sent = 0;
        // Pendientes de revisión, por inmobiliaria, una vez por semana.
        Document::where('status', 'pending')->where('created_at', '<=', now()->subDays(2))->select('tenant_id')->distinct()->pluck('tenant_id')->each(function ($tid) use (&$sent) {
            if (!self::adminOn((int) $tid)) {
                return;
            }
            try {
                $docs = Document::where('tenant_id', $tid)->where('status', 'pending')->where('created_at', '<=', now()->subDays(2))->with(['property', 'owner', 'client', 'agreement'])->orderBy('created_at')->limit(15)->get();
                $total = Document::where('tenant_id', $tid)->where('status', 'pending')->count();
                \Aero\Notify\Classes\Notify::fire('realestate.documents.review_pending', [
                    'count' => (string) $total, 'list' => $docs->map(fn ($d) => '• ' . $d->name . ' — ' . $d->subject)->implode("\n"),
                    'url' => \Backend::url('aero/realestate/documents'), 'tenant_name' => self::tenant($tid)?->name,
                ], ['tenant_id' => (int) $tid, 'dedup_key' => "realestate:docs-review:{$tid}:" . now()->format('oW')]);
                $sent++;
            } catch (\Throwable $e) {
                \Log::error("Aero.Realestate: no se pudo avisar documentos por revisar del tenant {$tid}: " . $e->getMessage());
            }
        });

        Document::whereNotNull('expires_on')->whereNull('expiry_alerted_at')->whereDate('expires_on', '<=', now()->addDays(30)->toDateString())
            ->whereDate('expires_on', '>=', now()->toDateString())->with(['property', 'owner', 'client', 'agreement'])->chunkById(100, function ($docs) use (&$sent) {
                foreach ($docs as $d) {
                    if (!self::adminOn($d->tenant_id)) {
                        continue;
                    }
                    try {
                        \Aero\Notify\Classes\Notify::fire('realestate.document.expiring', [
                            'document_name' => $d->name, 'subject' => $d->subject, 'expires_on' => $d->expires_on->format('d/m/Y'),
                            'url' => \Backend::url('aero/realestate/documents/update/' . $d->id), 'tenant_name' => self::tenant($d->tenant_id)?->name,
                        ], ['tenant_id' => (int) $d->tenant_id, 'dedup_key' => "realestate:doc:{$d->id}:expiring:" . $d->expires_on->toDateString()]);
                        Document::whereKey($d->id)->update(['expiry_alerted_at' => now()]);
                        $sent++;
                    } catch (\Throwable $e) {
                        \Log::error("Aero.Realestate: no se pudo avisar el vencimiento del documento {$d->id}: " . $e->getMessage());
                    }
                }
            });

        return $sent;
    }

    protected static function tenant($id)
    {
        try {
            return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($id) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function context(Contract $c): array
    {
        $tenant = null;
        try {
            $tenant = class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($c->tenant_id) : null;
        } catch (\Throwable $e) {
            // Sites no disponible: el aviso sale sin nombre del tenant
        }

        return [
            'code'             => $c->code,
            'property_title'   => (string) ($c->property?->title ?? ''),
            'property_address' => (string) ($c->property?->address ?? ''),
            'capital'          => number_format((float) $c->capital, 2, ',', '.') . ' ' . $c->currency,
            'end_date'         => $c->end_date?->format('d/m/Y') ?? '',
            'days_left'        => (string) max(0, (int) $c->days_left),
            'owner_name'       => (string) ($c->owner?->name ?? ''),
            'client_name'      => (string) ($c->client?->name ?? ''),
            'agent_name'       => (string) ($c->agent?->name ?? ''),
            'business_name'    => (string) ($tenant?->name ?? ''),
            'tenant_name'      => $tenant?->name,
            'url'              => \Backend::url('aero/realestate/contracts/update/' . $c->id),
        ];
    }
}
