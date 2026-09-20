<?php namespace Aero\Wapi\Jobs;

use Aero\Hello\Classes\Notifications\MessageDispatcher;
use Aero\Hello\Models\Contact;
use Aero\Hello\Models\ContactIdentity;
use Aero\Hello\Models\Conversation;
use Aero\Hello\Models\Message;
use Aero\Hello\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Registra en Hello un mensaje que se envió DESDE OTRO DISPOSITIVO de la cuenta
 * (el celular o WhatsApp Web) — wapi lo entrega como `message_create` con
 * fromMe. Sin esto la conversación solo mostraba lo que escribía el cliente y
 * lo que salía de Hello.
 *
 * El mismo evento llega también para los mensajes que Hello envió por la API,
 * así que antes de crear nada se concilia:
 *  1. ya hay un mensaje con ese id externo → se ignora;
 *  2. hay un envío reciente de Hello sin id externo y con el mismo texto → es
 *     ese: se le pone el id y no se duplica;
 *  3. si no, es un mensaje nuevo del dueño: se crea como saliente.
 */
class ProcessOutboundEchoJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(protected int $webhookEventId)
    {
    }

    public function handle(MessageDispatcher $dispatcher): void
    {
        $event = WebhookEvent::find($this->webhookEventId);
        if (!$event || $event->processed_at) {
            return;
        }

        try {
            $this->process($event, $dispatcher);
        } finally {
            $event->update(['processed_at' => now()]);
        }
    }

    protected function process(WebhookEvent $event, MessageDispatcher $dispatcher): void
    {
        $account = $event->account;
        $payload = $event->payload;
        $data = $payload['data'] ?? [];
        $externalId = $data['id'] ?? null;
        $to = (string) ($data['to'] ?? '');

        // Grupos, estados, canales: Hello no modela esas conversaciones.
        if (!$account || !$externalId || $to === '' || preg_match('/@g\.us|broadcast|newsletter/', $to)) {
            return;
        }

        if (Message::where('account_id', $account->id)->where('zernio_message_id', $externalId)->exists()) {
            return;
        }

        $peer = explode('@', $to)[0];
        $tenantId = $account->effective_tenant_id;

        $identity = ContactIdentity::where('platform', $account->platform)->where('external_id', $peer)->first();
        if (!$identity) {
            $contact = Contact::create(['tenant_id' => $tenantId, 'name' => $peer]);
            $identity = ContactIdentity::create(['contact_id' => $contact->id, 'platform' => $account->platform, 'external_id' => $peer]);
        }

        $conversation = Conversation::where('account_id', $account->id)->where('contact_id', $identity->contact_id)->first()
            ?: Conversation::create(['tenant_id' => $tenantId, 'account_id' => $account->id, 'contact_id' => $identity->contact_id, 'status' => 'open']);

        // Tipo, texto y adjunto (descarga incluida) con el mismo parser del driver.
        $parsed = $dispatcher->make('wapi')->parseWebhook($account, $payload);
        $body = $parsed['body'] ?? null;

        $pending = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')->whereNull('zernio_message_id')->whereIn('status', ['queued', 'sent'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->when($body !== null && $body !== '', fn ($q) => $q->where('body', $body))
            ->latest('id')->first();

        if ($pending) {
            $pending->update(['zernio_message_id' => $externalId]);
            return;
        }

        Message::create([
            'conversation_id'   => $conversation->id,
            'account_id'        => $account->id,
            'contact_id'        => $identity->contact_id,
            'direction'         => 'outbound',
            'type'              => $parsed['type'] ?? 'text',
            'body'              => $body,
            'media_url'         => $parsed['media_url'] ?? null,
            'zernio_message_id' => $externalId,
            'status'            => 'sent',
            'sent_at'           => now(),
            'provider_payload'  => ['source' => 'external_device'],
        ]);

        // Sube en la lista; no cuenta como no leído: lo escribió el propio equipo.
        $conversation->update(['last_message_at' => now()]);

        Log::info('aero.wapi: mensaje enviado desde otro dispositivo registrado', ['account' => $account->id, 'external_id' => $externalId]);
    }
}
