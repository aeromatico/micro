<?php namespace Aero\Livechat\Http\Controllers;

use Aero\Hello\Models\Account;
use Aero\Hello\Models\Conversation as HelloConversation;
use Aero\Livechat\Classes\ConversationLifecycle;
use Aero\Livechat\Classes\HelloBridge;
use Aero\Livechat\Models\ChannelSettings;
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

    /** GET api/v1/chat/livechat/settings — puente a WhatsApp del tenant y cuentas de Hello elegibles. */
    public function settings(Request $request): JsonResponse
    {
        if ($err = $this->denyUnlessCanConfigure($request)) {
            return $err;
        }

        $tenantId = (int) $request->attributes->get('tenant_id');

        return response()->json(['data' => $this->settingsPayload($tenantId)]);
    }

    /** POST api/v1/chat/livechat/settings {livechat_enabled?, enabled, account_id, to} */
    public function saveSettings(Request $request): JsonResponse
    {
        if ($err = $this->denyUnlessCanConfigure($request)) {
            return $err;
        }

        $tenantId = (int) $request->attributes->get('tenant_id');
        $settings = ChannelSettings::forScope($tenantId);

        if ($request->has('livechat_enabled')) {
            $settings->livechat_enabled = $request->boolean('livechat_enabled');
        }
        if ($request->has('widget_mode')) {
            $settings->widget_mode = (string) $request->input('widget_mode');
            $settings->widget_whatsapp = trim((string) $request->input('widget_whatsapp')) ?: null;
            $settings->custom_code = $request->input('custom_code') ?: null;
        }
        $settings->whatsapp_enabled = $request->boolean('enabled');
        $settings->hello_account_id = $request->input('account_id') ?: null;
        $settings->whatsapp_to = trim((string) $request->input('to')) ?: null;

        try {
            $settings->save();
        } catch (\October\Rain\Database\ModelException|\Illuminate\Validation\ValidationException $e) {
            $errors = method_exists($e, 'getErrors') ? $e->getErrors()->all() : (method_exists($e, 'errors') ? collect($e->errors())->flatten()->all() : [$e->getMessage()]);

            return response()->json(['error' => 'validation_failed', 'message' => $errors[0] ?? 'Datos inválidos.'], 422);
        }

        return response()->json(['data' => $this->settingsPayload($tenantId)]);
    }

    protected function settingsPayload(int $tenantId): array
    {
        return [
            'settings' => ChannelSettings::forScope($tenantId)->toPayload(),
            'accounts' => ChannelSettings::accountsFor($tenantId)->map(fn ($a) => [
                'id'     => $a->id,
                'label'  => $a->label,
                'driver' => $a->driver,
                'status' => $a->status,
            ])->values()->all(),
        ];
    }

    protected function denyUnlessCanConfigure(Request $request): ?JsonResponse
    {
        $user = $request->attributes->get('chat_user');

        return ($user && $user->hasAccess('aero.livechat.manage_settings'))
            ? null
            : response()->json(['error' => 'forbidden', 'message' => 'No tienes permiso para configurar Livechat.'], 403);
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
