<?php namespace Aero\Wapi\Http\Controllers\Api;

use Aero\Hello\Jobs\ProcessWebhookEventJob;
use Aero\Hello\Models\Account;
use Aero\Hello\Models\WebhookEvent;
use Aero\Wapi\Classes\WapiWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Recibe los webhooks que wapi entrega a la URL registrada con
 * `POST /v1/webhooks` (ver Settings::webhookUrl()). A diferencia de Zernio,
 * el id de cuenta va en la URL, no en el payload — wapi no tiene un bloque
 * `account` como el de Zernio — así que acá se resuelve la cuenta antes de
 * encolar el job y se la deja en WebhookEvent.account_id para que
 * ProcessWebhookEventJob no tenga que re-derivarla.
 */
class WapiWebhookController extends Controller
{
    public function handle(string $instanceId, Request $request, WapiWebhookVerifier $verifier): JsonResponse
    {
        if (!$verifier->isValid($request->getContent(), $request->header('X-Webhook-Signature'))) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $account = Account::ofDriver('wapi')->where('zernio_account_id', $instanceId)->first();

        if (!$account) {
            return response()->json(['error' => 'unknown_instance'], 404);
        }

        $payload = $request->all();
        $eventType = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        // Vocabulario que entiende ProcessWebhookEventJob (el de Zernio para los
        // mensajes; message.status y account.status son propios de los drivers
        // sin API oficial).
        // Lo que el dueño envía desde otro dispositivo (celular, WhatsApp Web).
        // También llega el eco de lo que Hello mismo envió por la API, así que
        // no va por el flujo de mensajes entrantes: un job propio lo concilia.
        if ($eventType === 'message_create') {
            return $this->handleEcho($account, $instanceId, $payload);
        }

        $canonical = match ($eventType) {
            'message'                      => 'message.received',
            'message_ack'                  => 'message.status',
            'disconnected', 'authenticated' => 'account.status',
            default                        => $eventType,
        };

        // wapi no manda un id de evento global (a diferencia de Zernio); se
        // sintetiza uno estable: el id del mensaje, o mensaje+ack (un mismo
        // mensaje pasa por varios acks y no deben deduplicarse entre sí), o el
        // timestamp de la entrega para el resto (qr, authenticated, ...).
        $eventKey = $data['id']
            ?? (isset($data['messageId']) ? $data['messageId'] . ':' . ($data['ack'] ?? '') : null)
            ?? $payload['timestamp']
            ?? now()->timestamp;
        $eventId = sprintf('wapi:%s:%s:%s', $instanceId, $eventType, $eventKey);

        $event = WebhookEvent::firstOrNew(['event_id' => $eventId]);
        $alreadyKnown = $event->exists;

        if (!$alreadyKnown) {
            $event->fill([
                'event_type' => $canonical,
                'account_id' => $account->id,
                'payload'    => $payload,
            ])->save();

            ProcessWebhookEventJob::dispatch($event->id);
        }

        return response()->json(['received' => true]);
    }

    protected function handleEcho(Account $account, string $instanceId, array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];

        if (empty($data['fromMe']) || empty($data['id'])) {
            return response()->json(['received' => true]);
        }

        $event = WebhookEvent::firstOrNew(['event_id' => sprintf('wapi:%s:message_create:%s', $instanceId, $data['id'])]);

        if (!$event->exists) {
            $event->fill(['event_type' => 'message.echo', 'account_id' => $account->id, 'payload' => $payload])->save();

            // Unos segundos de margen: si lo envió Hello, SendMessageJob debe
            // alcanzar a guardar el id externo antes de que se concilie.
            \Aero\Wapi\Jobs\ProcessOutboundEchoJob::dispatch($event->id)->delay(now()->addSeconds(8));
        }

        return response()->json(['received' => true]);
    }
}
