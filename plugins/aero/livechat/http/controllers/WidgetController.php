<?php namespace Aero\Livechat\Http\Controllers;

use Aero\Livechat\Classes\AttachmentStorage;
use Aero\Livechat\Classes\TelegramBridge;
use Aero\Livechat\Classes\TranscriptMailer;
use Aero\Livechat\Classes\ValidatesJson;
use Aero\Livechat\Models\Contact;
use Aero\Livechat\Models\Conversation;
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

        $contact = null;
        if (!empty($data['visitor_token'])) {
            $contact = Contact::where('tenant_id', $inbox->tenant_id)
                ->where('visitor_token', $data['visitor_token'])
                ->first();
        }

        // Contacto nuevo (sin token todavía): el pre-chat form del widget ya
        // pide estos tres datos antes de llamar acá — se exigen también del
        // lado del servidor para no depender solo de la validación del JS.
        if (!$contact) {
            $data = $this->check($request, [
                'name'  => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:40',
            ]) + $data;

            $contact = Contact::create([
                'tenant_id' => $inbox->tenant_id,
                'name'      => $data['name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'],
            ]);
        }
        else {
            $contact->fill(array_filter([
                'name'  => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]));
        }
        $contact->last_seen_at = now();
        $contact->save();

        $conversation = Conversation::openFor($inbox->id, $contact->id)->first();
        $isNew = !$conversation;

        if (!$conversation) {
            $conversation = Conversation::create([
                'tenant_id'  => $inbox->tenant_id,
                'inbox_id'   => $inbox->id,
                'contact_id' => $contact->id,
                'page_url'   => $data['page_url'] ?? null,
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

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::CONTACT,
            'body'            => $data['body'],
        ]);

        $conversation->last_message_at = now();
        $conversation->agent_unread_count++;
        $conversation->status = Conversation::OPEN;
        $conversation->save();

        TelegramBridge::relay($conversation, $message, "👤 {$contact->display_name}:");

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

        $conversation->last_message_at = now();
        $conversation->agent_unread_count++;
        $conversation->status = Conversation::OPEN;
        $conversation->save();

        TelegramBridge::relayAttachment($conversation, $message, "👤 {$contact->display_name}:");

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

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::SYSTEM,
            'body'            => 'El visitante finalizó el chat.',
        ]);

        $conversation->status = Conversation::RESOLVED;
        $conversation->agent_unread_count++;
        $conversation->save();

        TelegramBridge::relay($conversation, $message, 'ℹ️');

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

        return response()->json(['messages' => $this->serializeMessages($conversation, $data['after_id'] ?? null)]);
    }

    public function unread(Request $request)
    {
        $data = $this->check($request, [
            'widget_key'    => 'required|uuid',
            'visitor_token' => 'required|string|max:40',
        ]);

        [$inbox, $contact, $conversation] = $this->resolveConversation($data['widget_key'], $data['visitor_token']);

        return response()->json(['unread' => $conversation?->visitor_unread_count ?? 0]);
    }

    /** @return array{0: ?Inbox, 1: ?Contact, 2: ?Conversation} */
    protected function resolveConversation(string $widgetKey, string $visitorToken): array
    {
        $inbox = Inbox::where('widget_key', $widgetKey)->first();
        if (!$inbox) {
            return [null, null, null];
        }

        $contact = Contact::where('tenant_id', $inbox->tenant_id)->where('visitor_token', $visitorToken)->first();
        if (!$contact) {
            return [$inbox, null, null];
        }

        $conversation = Conversation::openFor($inbox->id, $contact->id)->first();

        return [$inbox, $contact, $conversation];
    }

    protected function serializeMessages(Conversation $conversation, ?int $afterId = null): array
    {
        return $conversation->messages()
            ->when($afterId, fn ($q) => $q->where('id', '>', $afterId))
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
                ] : null,
            ])
            ->all();
    }
}
