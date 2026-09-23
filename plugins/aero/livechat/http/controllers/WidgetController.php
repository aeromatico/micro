<?php namespace Aero\Livechat\Http\Controllers;

use Aero\Livechat\Classes\TelegramBridge;
use Aero\Livechat\Classes\ValidatesJson;
use Aero\Livechat\Models\Contact;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Inbox;
use Aero\Livechat\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        if (!$contact) {
            $contact = Contact::create([
                'tenant_id' => $inbox->tenant_id,
                'name'      => $data['name'] ?? null,
                'email'     => $data['email'] ?? null,
            ]);
        }
        else {
            $contact->fill(array_filter([
                'name'  => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
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
            ])
            ->all();
    }
}
