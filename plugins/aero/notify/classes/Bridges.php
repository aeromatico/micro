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

            Notify::fire('hello.message.received', [
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
