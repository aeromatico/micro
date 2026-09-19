<?php namespace Aero\Chat\Classes;

use Aero\Chat\Models\ChatCharge;
use Aero\Chat\Models\ChatEvent;
use Aero\Hello\Classes\ApiCredits;
use Aero\Hello\Classes\MessageComposer;
use Aero\Hello\Models\Conversation;
use Illuminate\Support\Facades\Log;

/**
 * Procesa el pago de un cobro hecho desde el chat. Lo llama el evento
 * `aero.pay.paymentReceived` (webhook del banco o conciliación): marca el
 * cobro como pagado, deja constancia en el hilo para que el equipo la vea y,
 * si el agente lo pidió, avisa al cliente.
 */
class ChargeSettler
{
    public static function handle($payment, $qrCode): void
    {
        static::handleOrder($qrCode);

        $charge = ChatCharge::where('qr_code_id', $qrCode->id)->where('status', 'pending')->first();
        if (!$charge) {
            return;
        }

        // Atómico: el webhook y la conciliación pueden llegar a la vez.
        $won = ChatCharge::where('id', $charge->id)->where('status', 'pending')->update(['status' => 'paid', 'paid_at' => now()]);
        if (!$won) {
            return;
        }

        $amount = static::money($charge->amount, $charge->currency);
        $who = $payment->sender_name ? ' de ' . $payment->sender_name : '';

        ChatEvent::create([
            'tenant_id' => $charge->tenant_id, 'conversation_id' => $charge->conversation_id, 'user_id' => null,
            'type' => 'payment', 'body' => 'Pago recibido' . $who . ': ' . $amount . ($charge->description ? ' · ' . $charge->description : ''),
            'data' => ['charge_id' => $charge->id, 'qr_code_id' => $qrCode->id],
        ]);

        // Que la conversación suba en la lista y quien la atiende la note.
        Conversation::where('id', $charge->conversation_id)->increment('unread_count');
        Conversation::where('id', $charge->conversation_id)->update(['last_message_at' => now()]);

        if ($charge->notify_on_paid) {
            static::thank($charge, $amount);
        }
    }

    /**
     * Pedido de la tienda creado desde el chat: la tienda ya lo marca pagado
     * por su cuenta (Aero\Shop bridge); acá se deja constancia en la
     * conversación y se agradece al cliente, una sola vez.
     */
    protected static function handleOrder($qrCode): void
    {
        if (!class_exists(\Aero\Shop\Models\Order::class)) {
            return;
        }

        $order = \Aero\Shop\Models\Order::where('tenant_id', $qrCode->tenant_id)->where('payment_reference', $qrCode->internal_reference)->first();
        $link = $order ? \Aero\Chat\Models\ChatOrder::where('order_id', $order->id)->first() : null;
        if (!$link) {
            return;
        }

        $won = \Aero\Chat\Models\ChatOrder::where('id', $link->id)->whereNull('paid_notified_at')->update(['paid_notified_at' => now()]);
        if (!$won) {
            return;
        }

        $amount = static::money($order->grand_total, $order->currency?->code);
        ChatEvent::create(['tenant_id' => $link->tenant_id, 'conversation_id' => $link->conversation_id, 'user_id' => null, 'type' => 'payment',
            'body' => 'Pedido ' . $order->order_number . ' pagado: ' . $amount, 'data' => ['order_id' => $order->id]]);
        Conversation::where('id', $link->conversation_id)->increment('unread_count');
        Conversation::where('id', $link->conversation_id)->update(['last_message_at' => now()]);

        if ($link->notify_on_paid) {
            try {
                $conv = Conversation::with('account')->find($link->conversation_id);
                $tx = ApiCredits::charge($link->tenant_id);
                MessageComposer::sendToContact($conv->account, $conv->contact_id, '¡Recibimos el pago de tu pedido ' . $order->order_number . ' (' . $amount . ')! Gracias por tu compra.', ['credit_transaction_id' => $tx]);
            } catch (\Throwable $e) {
                Log::warning('aero.chat: no se pudo avisar el pago del pedido ' . $order->order_number . ': ' . $e->getMessage());
            }
        }
    }

    protected static function thank(ChatCharge $charge, string $amount): void
    {
        try {
            $conv = Conversation::with('account')->find($charge->conversation_id);
            if (!$conv || !$conv->account) {
                return;
            }

            $tx = ApiCredits::charge($charge->tenant_id);
            MessageComposer::sendToContact(
                $conv->account, $conv->contact_id,
                '¡Recibimos tu pago de ' . $amount . '! Gracias.' . ($charge->description ? "\n" . $charge->description : ''),
                ['credit_transaction_id' => $tx]
            );
        } catch (\Throwable $e) {
            // Pasa, por ejemplo, con la ventana de 24 h cerrada o sin créditos:
            // el pago ya quedó registrado, el aviso es un extra.
            Log::warning('aero.chat: no se pudo avisar el pago al cliente (cobro ' . $charge->id . '): ' . $e->getMessage());
        }
    }

    public static function money($amount, ?string $currency): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' ' . ($currency ?: 'BOB');
    }
}
