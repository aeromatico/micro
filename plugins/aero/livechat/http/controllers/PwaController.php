<?php namespace Aero\Livechat\Http\Controllers;

use Aero\Hello\Models\Account;
use Aero\Hello\Models\Conversation as HelloConversation;
use Aero\Livechat\Classes\ConversationLifecycle;
use Aero\Livechat\Classes\HelloBridge;
use Aero\Livechat\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Acciones de Livechat que no existen para ningún otro canal (WhatsApp/Zernio/
 * wapi no tienen "finalizar" ni "banear"), expuestas al PWA de aero/chat (tema
 * whatsapp). Vive acá — no en aero/chat — y reutiliza su mismo middleware de
 * token (AuthenticateChatToken) para no duplicar la sesión del agente; ver
 * Plugin::$require (Aero.Chat) y routes.php.
 */
class PwaController extends Controller
{
    /** POST api/v1/chat/livechat/conversations/{id}/finish */
    public function finish(Request $request, $id): JsonResponse
    {
        [$conversation, $err] = $this->resolve($request, $id);
        if ($err) {
            return $err;
        }

        ConversationLifecycle::finish($conversation, 'El agente finalizó el chat.');

        return response()->json(['data' => ['ok' => true]]);
    }

    /** POST api/v1/chat/livechat/conversations/{id}/ban {hours?: int} — sin `hours`, ban permanente. */
    public function ban(Request $request, $id): JsonResponse
    {
        [$conversation, $err] = $this->resolve($request, $id);
        if ($err) {
            return $err;
        }

        $hours = $request->input('hours');
        if ($hours !== null && (!is_numeric($hours) || $hours < 1 || $hours > 8760)) {
            return response()->json(['error' => 'validation_failed', 'message' => 'Horas inválidas (1 a 8760), o vacío para un ban permanente.'], 422);
        }
        $hours = $hours !== null ? (int) $hours : null;

        $contact = $conversation->contact;
        $contact->ban($hours);

        $agent = $request->attributes->get('chat_user');
        $label = $hours ? "por {$hours} h" : 'de forma permanente';

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::SYSTEM,
            'body'            => "🚫 Visitante baneado {$label} por " . (trim($agent->first_name . ' ' . $agent->last_name) ?: $agent->login) . '.',
        ]);
        $conversation->last_message_at = now();
        $conversation->save();

        return response()->json(['data' => [
            'ok'           => true,
            'banned_until' => optional($contact->banned_until)->toIso8601String(),
        ]]);
    }

    /** @return array{0: ?\Aero\Livechat\Models\Conversation, 1: ?JsonResponse} */
    protected function resolve(Request $request, $id): array
    {
        $tenantId = (int) $request->attributes->get('tenant_id');

        $accountIds = Account::forTenant($tenantId)->ofDriver('livechat')->pluck('id');
        $helloConversation = HelloConversation::whereIn('account_id', $accountIds)->find($id);

        $conversation = $helloConversation
            ? HelloBridge::conversationFromExternalId($helloConversation->zernio_conversation_id)
            : null;

        if (!$conversation) {
            return [null, response()->json(['error' => 'not_found', 'message' => 'No encontrado.'], 404)];
        }

        return [$conversation, null];
    }
}
