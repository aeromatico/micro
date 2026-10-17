<?php namespace Aero\Notify\Classes;

use Event;

/**
 * Puentes de eventos de otros plugins hacia el catálogo de Notify, para los
 * casos en que el plugin de origen ya emite un evento propio (Pay, Hello) o
 * tiene un modelo con ciclo de vida claro (CRM). Así esos plugins no tienen
 * que conocer a Notify. Todo se guarda con class_exists() y nunca lanza: una
 * notificación fallida no puede romper el webhook o el guardado que la origina.
 */
class Bridges
{
    public static function register(): void
    {
        Event::listen('aero.pay.paymentReceived', fn ($payment, $qr) => static::safe(function () use ($payment, $qr) {
            // Las recargas de monedas cobran en la cuenta de la PLATAFORMA (pertenece a un tenant):
            // avisarle "pago recibido" (con el nombre del pagador) a los admins de ese tenant sería
            // filtrar ventas ajenas. Aero.Credits avisa por su cuenta (credits.purchase.paid).
            if (($qr->origin ?? null) === 'credits') {
                return;
            }

            Notify::fire('pay.payment.received', [
                'amount'      => number_format((float) $payment->amount, 2),
                'currency'    => $payment->currency,
                'payer_name'  => $payment->sender_name,
                'reference'   => $qr->internal_reference ?? $payment->qr_reference,
                'paid_at'     => optional($payment->payment_date)->format('d/m/Y'),
                'tenant_name' => static::tenantName($payment->tenant_id),
            ], ['tenant_id' => (int) $payment->tenant_id, 'dedup_key' => 'payment:' . $payment->id]);
        }));

        Event::listen('aero.hello.messageReceived', fn ($message) => static::safe(function () use ($message) {
            $message->loadMissing(['contact', 'account']);
            $tenantId = (int) $message->account?->tenant_id;

            // Enlace directo al chat en la PWA (/{espacio}/{codigo}); sin él, el push abre solo la lista.
            $conv = \Aero\Hello\Models\Conversation::find($message->conversation_id, ['id', 'tenant_id', 'code', 'is_muted']);

            // Un chat archivado queda silenciado (is_muted, ver InboxController::archive) — no
            // debe avisar por push ni sumar a la bandeja de notificaciones del agente.
            if ($conv?->is_muted) {
                return;
            }

            $urlTenant = $tenantId ?: (int) $conv?->tenant_id;
            $handle = $urlTenant ? \Aero\Sites\Models\Tenant::find($urlTenant)?->handle : null;
            $code = $conv?->code;

            Notify::fire('hello.message.received', [
                'url'          => $handle && $code ? 'https://chat.market.com.bo/' . $handle . '/' . $code : null,
                'contact_name' => $message->contact?->name ?: 'Contacto',
                'platform'     => $message->account?->platform ?? 'chat',
                'preview'      => mb_strimwidth((string) $message->body, 0, 140, '…') ?: '[' . ($message->type ?? 'adjunto') . ']',
                'tenant_name'  => static::tenantName($tenantId),
            ], ['tenant_id' => $tenantId, 'dedup_key' => 'conversation:' . $message->conversation_id]);
        }));

        Event::listen('aero.hello.accountStatus', fn ($account, $name) => static::safe(function () use ($account, $name) {
            if ($name !== 'disconnected') {
                return;
            }

            Notify::fire('hello.account.disconnected', [
                'account_label' => $account->label ?: $account->external_username ?: "#{$account->id}",
                'platform'      => $account->platform,
                'tenant_name'   => static::tenantName($account->tenant_id),
            ], ['tenant_id' => (int) $account->tenant_id, 'dedup_key' => 'account:' . $account->id . ':' . now()->format('YmdH')]);
        }));

        Event::listen('aero.hello.callMissed', fn ($call) => static::safe(function () use ($call) {
            $call->loadMissing('contact');

            Notify::fire('hello.call.missed', [
                'from_number'  => $call->from_number ?? '',
                'contact_name' => $call->contact?->name,
                'started_at'   => optional($call->started_at)->format('d/m/Y H:i'),
                'tenant_name'  => static::tenantName($call->tenant_id),
            ], ['tenant_id' => (int) $call->tenant_id, 'dedup_key' => 'call:' . $call->id]);
        }));

        // Stock bajo: solo al CRUZAR el umbral hacia abajo, no en cada venta posterior.
        if (class_exists(\Aero\Shop\Models\StockMovement::class)) {
            \Aero\Shop\Models\StockMovement::extend(function ($move) {
                $move->bindEvent('model.afterCreate', fn () => static::safe(function () use ($move) {
                    $threshold = \Aero\Shop\Models\ShopSettings::where('tenant_id', $move->tenant_id)->value('low_stock_threshold');

                    if ($threshold === null || $move->quantity_delta >= 0
                        || $move->quantity_after > $threshold || $move->quantity_after - $move->quantity_delta <= $threshold) {
                        return;
                    }

                    $move->loadMissing(['product', 'variant']);

                    Notify::fire('shop.stock.low', [
                        'product_name' => trim($move->product?->name . ' ' . ($move->variant?->name ?? '')),
                        'stock'        => $move->quantity_after,
                        'threshold'    => $threshold,
                        'tenant_name'  => static::tenantName($move->tenant_id),
                    ], ['tenant_id' => (int) $move->tenant_id, 'dedup_key' => 'stock:' . $move->id]);
                }));
            });
        }

        if (class_exists(\Aero\Crm\Models\Lead::class)) {
            \Aero\Crm\Models\Lead::extend(function ($lead) {
                $lead->bindEvent('model.afterCreate', fn () => static::safe(function () use ($lead) {
                    Notify::fire('crm.lead.created', [
                        'lead_name'   => $lead->name,
                        'source'      => $lead->source,
                        'tenant_name' => static::tenantName($lead->tenant_id),
                    ], ['tenant_id' => (int) $lead->tenant_id, 'dedup_key' => 'lead:' . $lead->id]);
                }));
            });
        }

        if (class_exists(\Aero\Crm\Models\Deal::class)) {
            \Aero\Crm\Models\Deal::extend(function ($deal) {
                $deal->bindEvent('model.afterSave', fn () => static::safe(function () use ($deal) {
                    if (!$deal->wasChanged('status') || !in_array($deal->status, ['won', 'lost'], true)) {
                        return;
                    }

                    $ctx = ['deal_name' => $deal->title, 'tenant_name' => static::tenantName($deal->tenant_id)];

                    Notify::fire('crm.deal.' . $deal->status, $ctx + ($deal->status === 'won'
                        ? ['amount' => number_format((float) $deal->value, 2) . ' ' . $deal->currency]
                        : []), ['tenant_id' => (int) $deal->tenant_id, 'dedup_key' => "deal:{$deal->id}:{$deal->status}"]);
                }));
            });
        }

        if (class_exists(\Aero\Community\Models\Post::class)) {
            static::registerCommunity();
        }

        static::registerSupport();

        if (class_exists(\Aero\Plan\Models\Plan::class)) {
            static::registerPlan();
        }
    }

    /**
     * Aero.Plan → Notify. El dueño avisa a los superadmin y el equipo avisa al
     * dueño (admins del tenant). Nunca debe romper una acción del plan.
     */
    protected static function registerPlan(): void
    {
        $ctx = fn ($plan, $step = null, array $extra = []) => array_filter([
            'tenant_name' => static::tenantName($plan->tenant_id),
            'day'         => (string) $plan->day,
            'step_number' => $step ? (string) $step->number : null,
            'step_name'   => $step ? (\Aero\Plan\Classes\Stages::find($step->number)['name'] ?? null) : null,
            'url'         => \Backend::url('aero/plan/plans/review/' . $plan->id),
        ] + $extra, fn ($v) => $v !== null);
        $owner = fn ($plan, $step = null, array $extra = []) => ['url' => \Backend::url('aero/plan/plan')] + $ctx($plan, $step, $extra);
        $opts = fn ($plan, string $key) => ['tenant_id' => (int) $plan->tenant_id, 'dedup_key' => 'plan:' . $plan->id . ':' . $key];
        $stepName = fn (int $n) => \Aero\Plan\Classes\Stages::find($n)['name'] ?? null;

        Event::listen('aero.plan.stageSubmitted', fn ($plan, $step) => static::safe(fn () => Notify::fire(
            'plan.step.submitted', $ctx($plan, $step), $opts($plan, 'submitted:' . $step->number . ':' . optional($step->submitted_at)->timestamp)
        )));

        Event::listen('aero.plan.stageApproved', fn ($plan, $step, $next) => static::safe(function () use ($plan, $step, $next, $owner, $opts, $stepName) {
            if (!$next) {
                return; // el último paso lo avisa aero.plan.completed
            }
            Notify::fire('plan.step.approved', $owner($plan, $step, ['next_step' => $stepName($next->number)]), $opts($plan, 'approved:' . $step->number));
        }));

        Event::listen('aero.plan.changesRequested', fn ($plan, $step, $note) => static::safe(fn () => Notify::fire(
            'plan.changes.requested', $owner($plan, $step, ['message' => \Illuminate\Support\Str::limit((string) $note, 400)]),
            $opts($plan, 'changes:' . $step->number . ':' . md5((string) $note))
        )));

        Event::listen('aero.plan.messagePosted', fn ($plan, $message) => static::safe(function () use ($plan, $message, $ctx, $owner, $opts) {
            $step = $plan->steps()->where('number', $message->step_number)->first();
            $text = ['message' => \Illuminate\Support\Str::limit((string) $message->body, 400)];

            $message->author === 'team'
                ? Notify::fire('plan.message.from_team', $owner($plan, $step, $text), $opts($plan, 'msg:' . $message->id))
                : Notify::fire('plan.message.from_client', $ctx($plan, $step, $text), $opts($plan, 'msg:' . $message->id));
        }));

        Event::listen('aero.plan.paused', fn ($plan) => static::safe(fn () => Notify::fire(
            'plan.paused', $ctx($plan), $opts($plan, 'paused:' . optional($plan->paused_at)->timestamp)
        )));

        Event::listen('aero.plan.resumed', fn ($plan) => static::safe(function () use ($plan, $ctx, $opts) {
            $open = $plan->steps()->whereIn('status', ['open', 'review', 'changes'])->orderBy('number')->first();

            Notify::fire('plan.resumed', $ctx($plan, $open), $opts($plan, 'resumed:' . now()->timestamp));
        }));

        Event::listen('aero.plan.completed', fn ($plan) => static::safe(fn () => Notify::fire(
            'plan.completed', $owner($plan), $opts($plan, 'completed')
        )));
    }

    /** Aero.Community → Notify. Nunca debe romper el guardado de una publicación. */
    protected static function registerCommunity(): void
    {
        $base = fn ($post) => [
            'title'       => $post->title,
            'type_label'  => $post->type_label,
            'author_name' => $post->author_name,
            'area'        => $post->area,
            'status'      => $post->status_label,
            'due_at'      => optional($post->sla_due_at)->format('d/m H:i'),
            'url'         => \Backend::url('aero/community/posts/update/' . $post->id),
            'tenant_name' => static::tenantName($post->tenant_id),
        ];
        $author = function ($post) {
            $user = $post->user_id ? \Backend\Models\User::find($post->user_id) : null;

            return $user ? ['name' => $user->full_name, 'email' => $user->email, 'user_id' => $user->id] : null;
        };

        Event::listen('aero.community.postCreated', fn ($post) => static::safe(function () use ($post, $base) {
            // El equipo no necesita aviso de su propia publicación ni de charla del foro.
            if ($post->sla_due_at) {
                Notify::fire('community.post.created', $base($post), ['tenant_id' => (int) $post->tenant_id, 'dedup_key' => 'community:created:' . $post->id]);
            }
        }));

        Event::listen('aero.community.postOverdue', fn ($post) => static::safe(function () use ($post, $base) {
            Notify::fire('community.post.overdue', $base($post), ['tenant_id' => (int) $post->tenant_id, 'dedup_key' => 'community:overdue:' . $post->id]);
        }));

        Event::listen('aero.community.postUpdated', fn ($post, $statusChanged) => static::safe(function () use ($post, $statusChanged, $base, $author) {
            if (!$post->responded_at || !($actor = $author($post))) {
                return;
            }

            $ctx = $base($post) + ['response' => $post->official_response];
            Notify::fire('community.post.responded', $ctx, [
                'tenant_id' => (int) $post->tenant_id, 'actor' => $actor,
                'dedup_key' => 'community:responded:' . $post->id . ':' . md5($post->status . '|' . $post->official_response),
            ]);

            if ($statusChanged && $post->status === 'shipped') {
                $voters = \Aero\Community\Models\Vote::where('post_id', $post->id)->pluck('user_id');
                $adhoc = \Backend\Models\User::whereIn('id', $voters)->get()
                    ->map(fn ($u) => ['name' => $u->full_name, 'email' => $u->email, 'user_id' => $u->id])->all();
                if ($adhoc) {
                    Notify::fire('community.post.shipped', $ctx, ['tenant_id' => (int) $post->tenant_id, 'adhoc' => $adhoc, 'dedup_key' => 'community:shipped:' . $post->id]);
                }
            }
        }));

        Event::listen('aero.community.commentAdded', fn ($comment, $post) => static::safe(function () use ($comment, $post, $base, $author) {
            $actor = $author($post);
            // No avisar al autor de su propio comentario.
            if (!$actor || (int) $actor['user_id'] === (int) $comment->user_id) {
                return;
            }

            Notify::fire('community.comment.added', $base($post) + ['author_name' => $comment->author_name, 'comment' => \Illuminate\Support\Str::limit($comment->body, 200)], [
                'tenant_id' => (int) $post->tenant_id, 'actor' => $actor, 'dedup_key' => 'community:comment:' . $comment->id,
            ]);
        }));
    }

    /**
     * Tickets de soporte. Dos mesas que nunca se mezclan: el portal de cada
     * tenant (ticket->tenant_id) avisa a los admins de ese tenant y al correo
     * del área; la mesa de la plataforma (tenant_id null, origin_tenant_id)
     * avisa a los superadmin. Quien abrió el ticket recibe confirmación y las
     * respuestas del equipo.
     */
    protected static function registerSupport(): void
    {
        if (!class_exists(\Aero\Crm\Models\Ticket::class)) {
            return;
        }

        Event::listen('aero.crm.support.ticketCreated', fn ($ticket) => static::safe(function () use ($ticket) {
            $ctx = static::ticketContext($ticket) + ['message' => \Illuminate\Support\Str::limit((string) $ticket->description, 400)];
            $requester = static::ticketRequester($ticket);

            if ($requester) {
                Notify::fire('support.ticket.received', $ctx + ['url' => static::requesterUrl($ticket)], [
                    'tenant_id' => (int) $ticket->tenant_id, 'actor' => $requester, 'dedup_key' => 'ticket:received:' . $ticket->id,
                ]);
            }

            static::fireStaff($ticket, 'opened', 'platform_opened', $ctx, 'ticket:opened:' . $ticket->id);
        }));

        Event::listen('aero.crm.support.ticketReplied', fn ($ticket) => static::safe(function () use ($ticket) {
            $reply = $ticket->replies()->where('author_type', 'customer')->where('is_internal', false)->latest('id')->first();
            $ctx = static::ticketContext($ticket) + ['message' => \Illuminate\Support\Str::limit((string) $reply?->body, 400)];

            static::fireStaff($ticket, 'customer_replied', 'platform_customer_replied', $ctx, 'ticket:customer_replied:' . $ticket->id . ':' . ($reply?->id ?? 0));
        }));

        Event::listen('aero.crm.ticket.agentReplied', fn ($ticket, $reply) => static::safe(function () use ($ticket, $reply) {
            $requester = static::ticketRequester($ticket);
            if (!$requester) {
                return;
            }

            $ctx = static::ticketContext($ticket) + [
                'message'    => \Illuminate\Support\Str::limit((string) $reply->body, 600),
                'agent_name' => $reply->user?->full_name ?: 'Equipo de soporte',
                'url'        => static::requesterUrl($ticket),
            ];
            Notify::fire('support.ticket.agent_replied', $ctx, [
                'tenant_id' => (int) $ticket->tenant_id, 'actor' => $requester, 'dedup_key' => 'ticket:agent_replied:' . $reply->id,
            ]);
        }));
    }

    protected static function ticketContext($ticket): array
    {
        $tenantId = (int) ($ticket->tenant_id ?: $ticket->origin_tenant_id);

        return [
            'ticket_number'   => $ticket->number,
            'subject'         => $ticket->subject,
            'requester_name'  => $ticket->requester_name ?: 'Cliente',
            'requester_email' => $ticket->requester_email,
            'department'      => $ticket->department?->name,
            'tenant_name'     => static::tenantName($tenantId),
        ];
    }

    protected static function ticketRequester($ticket): ?array
    {
        if (!$ticket->requester_email) {
            return null;
        }

        return ['name' => $ticket->requester_name, 'email' => $ticket->requester_email];
    }

    /** Aviso al equipo: admins del tenant (+ correo del área) o superadmin si es de la plataforma. */
    protected static function fireStaff($ticket, string $tenantEvent, string $platformEvent, array $ctx, string $dedup): void
    {
        if ($ticket->tenant_id) {
            $adhoc = $ticket->department?->email ? [['name' => $ticket->department->name, 'email' => $ticket->department->email]] : [];

            Notify::fire('support.ticket.' . $tenantEvent, $ctx + ['url' => static::staffUrl($ticket)], [
                'tenant_id' => (int) $ticket->tenant_id, 'adhoc' => $adhoc, 'dedup_key' => $dedup,
            ]);

            return;
        }

        Notify::fire('support.ticket.' . $platformEvent, $ctx + ['url' => static::staffUrl($ticket)], [
            'tenant_id' => 0, 'dedup_key' => $dedup,
        ]);
    }

    protected static function staffUrl($ticket): string
    {
        return \Backend::url('aero/crm/tickets/update/' . $ticket->id);
    }

    /** Enlace para quien abrió el ticket: portal del tenant (con token si es invitado) o panel si es un tenant. */
    protected static function requesterUrl($ticket): ?string
    {
        if ($ticket->tenant_id) {
            $domain = \Aero\Sites\Models\Tenant::find($ticket->tenant_id)?->primary_domain;
            if (!$domain) {
                return null;
            }
            $url = 'https://' . $domain . '/soporte/ticket/' . $ticket->id;

            return !$ticket->frontend_user_id && $ticket->access_token ? $url . '?token=' . urlencode($ticket->access_token) : $url;
        }

        return \Backend::url('aero/crm/support/view/' . $ticket->id);
    }

    protected static function tenantName($tenantId): ?string
    {
        return $tenantId ? \Aero\Sites\Models\Tenant::find($tenantId)?->name : null;
    }

    protected static function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            \Log::error('Aero.Notify bridge: ' . $e->getMessage());
        }
    }
}
