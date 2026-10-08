<?php namespace Aero\Livechat\Classes;

use Aero\Hello\Classes\Hello;
use Aero\Hello\Classes\PhoneNumber;
use Aero\Livechat\Models\ChannelSettings;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Puente bidireccional entre una conversación del widget y el WhatsApp de un
 * agente, vía Aero.Hello (cualquier driver: wapi o Zernio). Misma idea que
 * TelegramBridge, pero WhatsApp no tiene Topics: varias conversaciones
 * comparten el mismo chat, así que cada mensaje saliente lleva su código
 * "#id" y la respuesta entrante se resuelve por ese código; sin código, cae
 * a la conversación abierta con actividad más reciente del tenant.
 *
 * Una falla de Hello nunca debe romper el chat del visitante: todo va en
 * try/catch y solo se registra.
 */
class WhatsappBridge
{
    /** Reenvía un mensaje (visitante, agente del panel o sistema) por WhatsApp, si el tenant lo tiene activo. */
    public static function relay(Conversation $conversation, Message $message, string $prefix): void
    {
        $settings = static::activeSettings($conversation->tenant_id);
        if (!$settings) {
            return;
        }

        $text = $message->hasAttachment()
            ? trim($message->attachment_name) . ' ' . $message->attachment_url
            : $message->body;

        $body = trim("{$prefix} #{$conversation->id}\n" . trim((string) $text));

        try {
            Hello::send($settings->whatsapp_to, $body, [
                'account_id' => $settings->hello_account_id,
                'platform'   => 'whatsapp',
                'tenant_id'  => $conversation->tenant_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('aero.livechat: no se pudo retransmitir por WhatsApp', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Handler de `aero.hello.messageReceived`: un mensaje entrante de Hello es
     * una respuesta de agente si llegó por la cuenta configurada desde el
     * número configurado.
     */
    public static function handleInbound($helloMessage): void
    {
        try {
            if (!$helloMessage || $helloMessage->direction !== 'inbound' || !trim((string) $helloMessage->body)) {
                return;
            }

            $settings = ChannelSettings::where('whatsapp_enabled', true)
                ->where('hello_account_id', $helloMessage->account_id)
                ->get()
                ->first(fn ($s) => $s->whatsapp_to && static::senderMatches($helloMessage, $s->whatsapp_to));

            if (!$settings) {
                return;
            }

            [$conversationId, $text] = static::parse((string) $helloMessage->body);
            $conversation = static::resolveConversation($settings->tenant_id, $conversationId);
            if (!$conversation || $text === '') {
                return;
            }

            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::AGENT,
                'sender_id'       => null,
                'body'            => $text,
            ]);

            $conversation->last_message_at = now();
            $conversation->visitor_unread_count++;
            $conversation->save();
        } catch (\Throwable $e) {
            Log::warning('aero.livechat: fallo al procesar respuesta de WhatsApp', ['error' => $e->getMessage()]);
        }
    }

    protected static function activeSettings(?int $tenantId): ?ChannelSettings
    {
        $settings = ChannelSettings::lookup($tenantId);

        return ($settings && $settings->whatsapp_enabled && $settings->hello_account_id && $settings->whatsapp_to) ? $settings : null;
    }

    protected static function senderMatches($helloMessage, string $to): bool
    {
        $from = $helloMessage->contact?->identities?->firstWhere('platform', 'whatsapp')?->external_id;

        return $from && PhoneNumber::normalize($from) === PhoneNumber::normalize($to);
    }

    /** "#12 hola" → [12, "hola"]; sin código → [null, texto]. */
    protected static function parse(string $body): array
    {
        $body = trim($body);

        if (preg_match('/^#(\d+)\s*(.*)$/s', $body, $m)) {
            return [(int) $m[1], trim($m[2])];
        }

        return [null, $body];
    }

    protected static function resolveConversation(?int $tenantId, ?int $id): ?Conversation
    {
        $query = Conversation::inScope($tenantId);

        if ($id) {
            return $query->find($id);
        }

        return $query->where('status', Conversation::OPEN)->orderByDesc('last_message_at')->first();
    }
}
