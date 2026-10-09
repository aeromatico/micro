<?php namespace Aero\Livechat\Http\Controllers;

use Aero\Livechat\Classes\AttachmentStorage;
use Aero\Livechat\Classes\ConversationLifecycle;
use Aero\Livechat\Classes\HelloBridge;
use Aero\Livechat\Classes\AgentBridges;
use Aero\Livechat\Classes\TranscriptMailer;
use Aero\Livechat\Classes\ValidatesJson;
use Aero\Livechat\Models\Contact;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\ChannelSettings;
use Aero\Livechat\Models\Inbox;
use Aero\Livechat\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Storage;

/**
 * API pública y anónima que consume `assets/js/widget.js` desde el dominio
 * del tenant. Sin autenticación: el aislamiento es por widget_key (público,
 * no secreto) + visitor_token (opaco, se guarda en localStorage del visitante).
 */
class WidgetController extends Controller
{
    use ValidatesJson;

    /**
     * Público: cómo debe pintarse el widget (modo, número de WhatsApp o código
     * personalizado). Solo sale lo que el modo del tenant usa. Un inbox
     * inexistente o desactivado devuelve enabled=false — el script no pinta nada.
     */
    public function config(Request $request)
    {
        $data = $this->check($request, ['widget_key' => 'required|uuid']);

        $inbox = Inbox::where('widget_key', $data['widget_key'])->where('is_active', true)->first();
        if (!$inbox) {
            return response()->json(['enabled' => false]);
        }

        $config = ChannelSettings::widgetConfig($inbox->tenant_id ? (int) $inbox->tenant_id : null);
        if ($config['whatsapp']) {
            $config['whatsapp'] = \Aero\Hello\Classes\PhoneNumber::normalize($config['whatsapp']);
        }

        return response()->json($config);
    }

    public function start(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'nullable|string|max:40',
            'name'          => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:40',
            'page_url'      => 'nullable|string|max:255',
        ]);

        $inbox = Inbox::where('widget_key', $data['widget_key'])->where('is_active', true)->first();
        if (!$inbox) {
            return response()->json(['error' => 'inbox_not_found'], 404);
        }
        if (!$inbox->isServing()) {
            return response()->json(['error' => 'livechat_disabled', 'message' => 'El chat no está disponible por ahora.'], 403);
        }

        $contact = null;
        if (!empty($data['visitor_token'])) {
            $contact = Contact::where('tenant_id', $inbox->tenant_id)
                ->where('visitor_token', $data['visitor_token'])
                ->first();
        }

        $isReturningWithoutToken = false;

        // Contacto nuevo (sin token todavía): el pre-chat form del widget ya
        // pide estos tres datos antes de llamar acá — se exigen también del
        // lado del servidor para no depender solo de la validación del JS.
        if (!$contact) {
            $data = $this->check($request, [
                'name'  => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:40',
            ]) + $data;

            // El visitante perdió su token local (otro dispositivo, borró datos
            // del sitio, modo incógnito, etc.) pero ya escribió antes con este
            // mismo correo o teléfono: se retoma su historial en la MISMA
            // conversación en vez de abrir un contacto y un chat duplicados. El
            // token nuevo que se le devuelve pasa a apuntar a ese contacto de
            // siempre — no se pierde, solo se re-vincula.
            // Email primero (más confiable, formato validado); teléfono como
            // respaldo solo si no hay match por correo — evita fusionar a dos
            // personas distintas que comparten un teléfono mal tipeado/formateado.
            $contact = Contact::where('tenant_id', $inbox->tenant_id)->where('email', $data['email'])
                ->orderByDesc('last_seen_at')->first()
                ?? Contact::where('tenant_id', $inbox->tenant_id)->where('phone', $data['phone'])
                ->orderByDesc('last_seen_at')->first();

            $isReturningWithoutToken = (bool) $contact;

            $contact ??= new Contact(['tenant_id' => $inbox->tenant_id]);
        }

        if ($contact->exists && $contact->isBanned()) {
            return response()->json(['error' => 'banned', 'message' => 'No puedes iniciar un chat en este momento.'], 403);
        }

        $contact->fill(array_filter([
            'name'  => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]));
        $contact->last_seen_at = now();
        $contact->save();

        $conversation = Conversation::openFor($inbox->id, $contact->id)->first();
        $isNew = !$conversation;

        if (!$conversation) {
            $conversation = Conversation::create([
                'tenant_id'          => $inbox->tenant_id,
                'inbox_id'           => $inbox->id,
                'contact_id'         => $contact->id,
                'page_url'           => $data['page_url'] ?? null,
                'session_started_at' => now(),
            ]);
        }
        elseif (!empty($data['page_url'])) {
            $conversation->page_url = $data['page_url'];
            $conversation->save();
        }

        if ($isNew && $inbox->welcome_message) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::SYSTEM,
                'body'            => $inbox->welcome_message,
            ]);
        }

        // Marca visible para el agente: mismo hilo de siempre, pero desde acá
        // es una sesión nueva (otro dispositivo/navegador) — no un mensaje del
        // visitante ni del agente, para no confundir quién dijo qué.
        // session_started_at mueve el corte de lo que el WIDGET le muestra al
        // visitante (ver serializeMessages) — el agente sigue viendo todo el
        // historial siempre, en el panel y en el omnichat.
        if ($isReturningWithoutToken && !$isNew) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => Message::SYSTEM,
                'body'            => '↻ Nueva sesión del visitante · ' . now()->format('d/m/Y H:i'),
            ]);
            $conversation->last_message_at = now();
            $conversation->session_started_at = now();
            $conversation->save();
        }

        $conversation->visitor_unread_count = 0;
        $conversation->save();

        return response()->json([
            'visitor_token'   => $contact->visitor_token,
            'conversation_id' => $conversation->id,
            'inbox'           => [
                'name'  => $inbox->name,
                'color' => $inbox->color,
            ],
            'messages' => $this->serializeMessages($conversation),
        ]);
    }

    public function message(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'required|string|max:40',
            'body'          => 'required|string|max:4000',
        ]);

        [$inbox, $contact, $conversation] = $this->resolveConversation($data['widget_key'], $data['visitor_token']);
        if (!$conversation) {
            return response()->json(['error' => 'conversation_not_found'], 404);
        }
        if ($contact->isBanned()) {
            return response()->json(['error' => 'banned', 'message' => 'No puedes seguir escribiendo en este chat.'], 403);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::CONTACT,
            'body'            => $data['body'],
        ]);

        // Reabre una conversación resuelta (auto-cierre o "Finalizar" manual):
        // desde el punto de vista del visitante, es una sesión nueva — no debe
        // ver el hilo cerrado anterior (ver serializeMessages).
        if ($conversation->status === Conversation::RESOLVED) {
            $conversation->session_started_at = now();
        }

        $conversation->last_message_at = now();
        $conversation->agent_unread_count++;
        $conversation->status = Conversation::OPEN;
        $conversation->save();

        AgentBridges::relay($conversation, $message, "👤 {$contact->display_name}:");
        HelloBridge::mirrorInbound($conversation, $message);

        return response()->json(['ok' => true, 'messages' => $this->serializeMessages($conversation)]);
    }

    public function attachment(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'required|string|max:40',
            'file'          => 'required|file',
        ]);

        [$inbox, $contact, $conversation] = $this->resolveConversation($data['widget_key'], $data['visitor_token']);
        if (!$conversation) {
            return response()->json(['error' => 'conversation_not_found'], 404);
        }
        if ($contact->isBanned()) {
            return response()->json(['error' => 'banned', 'message' => 'No puedes seguir escribiendo en este chat.'], 403);
        }

        $stored = AttachmentStorage::store($request->file('file'));
        if (isset($stored['error'])) {
            return response()->json(['error' => 'attachment_rejected', 'message' => $stored['error']], 422);
        }

        $message = Message::create([
            'conversation_id'  => $conversation->id,
            'sender_type'      => Message::CONTACT,
            'body'             => '',
            'attachment_path'  => $stored['path'],
            'attachment_name'  => $stored['name'],
            'attachment_mime'  => $stored['mime'],
            'attachment_size'  => $stored['size'],
            'attachment_token' => $stored['token'],
        ]);

        if ($conversation->status === Conversation::RESOLVED) {
            $conversation->session_started_at = now();
        }

        $conversation->last_message_at = now();
        $conversation->agent_unread_count++;
        $conversation->status = Conversation::OPEN;
        $conversation->save();

        AgentBridges::relayAttachment($conversation, $message, "👤 {$contact->display_name}:");
        HelloBridge::mirrorInbound($conversation, $message);

        return response()->json(['ok' => true, 'messages' => $this->serializeMessages($conversation)]);
    }

    /** Público por diseño: el token de 40 caracteres es la única llave, no hay endpoint que los liste. */
    public function attachmentDownload(string $token)
    {
        $message = Message::where('attachment_token', $token)->first();
        if (!$message || !Storage::disk('local')->exists($message->attachment_path)) {
            abort(404);
        }

        return AttachmentStorage::stream($message->attachment_path, $message->attachment_mime, $message->attachment_name);
    }

    public function transcript(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'required|string|max:40',
        ]);

        [$inbox, $contact, $conversation] = $this->resolveConversation($data['widget_key'], $data['visitor_token']);
        if (!$conversation) {
            return response()->json(['error' => 'conversation_not_found'], 404);
        }

        if (!$contact->email) {
            return response()->json(['error' => 'no_email', 'message' => 'No hay un correo registrado para este chat.'], 422);
        }

        TranscriptMailer::send($conversation, $contact, $inbox);

        return response()->json(['ok' => true]);
    }

    /**
     * El visitante termina la sesión desde el widget: la conversación queda
     * resuelta y el widget borra su token local — la próxima vez que abra el
     * chat empieza de cero (contacto y conversación nuevos), como si nunca
     * hubiera chateado. Si vuelve a escribir sin haber "finalizado", la
     * misma conversación se reabre sola (ver `message()`).
     */
    public function end(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'required|string|max:40',
        ]);

        [$inbox, $contact, $conversation] = $this->resolveConversation($data['widget_key'], $data['visitor_token']);
        if (!$conversation) {
            return response()->json(['error' => 'conversation_not_found'], 404);
        }

        $conversation->agent_unread_count++;
        $conversation->save();

        ConversationLifecycle::finish($conversation, 'El visitante finalizó el chat.');

        return response()->json(['ok' => true]);
    }

    public function messages(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'required|string|max:40',
            'after_id'      => 'nullable|integer',
        ]);

        [$inbox, $contact, $conversation] = $this->resolveConversation($data['widget_key'], $data['visitor_token']);
        if (!$conversation) {
            return response()->json(['error' => 'conversation_not_found'], 404);
        }

        // El visitante tiene el panel abierto: lo que llegó del agente queda leído.
        $conversation->visitor_unread_count = 0;
        $conversation->save();

        return response()->json([
            'messages' => $this->serializeMessages($conversation, $data['after_id'] ?? null),
            'status'   => $conversation->status,
        ]);
    }

    public function unread(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'required|string|max:40',
        ]);

        [$inbox, $contact, $conversation] = $this->resolveConversation($data['widget_key'], $data['visitor_token']);

        return response()->json([
            'enabled' => (bool) $inbox?->isServing(),
            'unread' => $conversation?->visitor_unread_count ?? 0,
            'status' => $conversation?->status,
        ]);
    }

    /** @return array{0: ?Inbox, 1: ?Contact, 2: ?Conversation} */
    protected function resolveConversation(string $widgetKey, string $visitorToken): array
    {
        $inbox = Inbox::where('widget_key', $widgetKey)->first();
        if (!$inbox) {
            return [null, null, null];
        }
        // Apagado desde Configuración: sin conversación resoluble, así message/
        // attachment/messages responden "no encontrada" y unread avisa al widget.
        if (!$inbox->isServing()) {
            return [$inbox, null, null];
        }

        $contact = Contact::where('tenant_id', $inbox->tenant_id)->where('visitor_token', $visitorToken)->first();
        if (!$contact) {
            return [$inbox, null, null];
        }

        $conversation = Conversation::openFor($inbox->id, $contact->id)->first();

        return [$inbox, $contact, $conversation];
    }

    /**
     * Lo que ve el VISITANTE — no el agente (el panel y el omnichat leen el
     * historial completo directo del modelo, sin pasar por acá). Solo la
     * sesión activa: desde `session_started_at` en adelante, si hay uno
     * marcado (conversación reabierta o retomada desde otro dispositivo — ver
     * start()/message()/attachment()). Sin marca (primera sesión de siempre),
     * se ve todo, no hay nada anterior que ocultar.
     */
    protected function serializeMessages(Conversation $conversation, ?int $afterId = null): array
    {
        return $conversation->messages()
            ->when($afterId, fn ($q) => $q->where('id', '>', $afterId))
            ->when($conversation->session_started_at, fn ($q) => $q->where('created_at', '>=', $conversation->session_started_at))
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => [
                'id'         => $m->id,
                'from'       => $m->sender_type,
                'body'       => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
                'attachment' => $m->hasAttachment() ? [
                    'url'   => $m->attachment_url,
                    'name'  => $m->attachment_name,
                    'mime'  => $m->attachment_mime,
                    'image' => $m->isImageAttachment(),
                    'audio' => $m->isAudioAttachment(),
                    'video' => $m->isVideoAttachment(),
                ] : null,
            ])
            ->all();
    }
}
