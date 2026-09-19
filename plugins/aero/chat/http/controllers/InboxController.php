<?php namespace Aero\Chat\Http\Controllers;

use Aero\Chat\Models\ChatEvent;
use Aero\Hello\Classes\ApiCredits;
use Aero\Hello\Classes\MessageComposer;
use Aero\Hello\Http\Resources\Transformers;
use Aero\Hello\Models\Account;
use Aero\Hello\Models\Conversation;
use Aero\Sites\Models\TenantUser;
use Backend\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InboxController extends Controller
{
    use \Aero\Chat\Classes\ValidatesJson;

    /** GET accounts — los números conectados del tenant. */
    public function accounts(Request $request)
    {
        $accounts = Account::with('profile')->forTenant($this->tenantId($request))->enabled()->orderBy('label')->get();

        $unread = Conversation::whereIn('account_id', $accounts->pluck('id'))
            ->selectRaw('account_id, SUM(unread_count) as n')->groupBy('account_id')->pluck('n', 'account_id');

        return $this->data($accounts->map(fn ($a) => Transformers::account($a) + ['unread' => (int) ($unread[$a->id] ?? 0)])->all());
    }

    /** GET agents — usuarios de backend con acceso al tenant y su carga. */
    public function agents(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        $ids = TenantUser::where('tenant_id', $tenant->id)->pluck('user_id')->push($tenant->backend_user_id)->filter()->unique();

        $load = Conversation::whereIn('account_id', $this->accountIds($request))->where('status', 'open')
            ->whereNotNull('assigned_to')->selectRaw('assigned_to, COUNT(*) as n')->groupBy('assigned_to')->pluck('n', 'assigned_to');

        $agents = User::whereIn('id', $ids)->where('is_activated', true)->orderBy('first_name')->get()
            ->map(fn ($u) => AuthController::user($u) + ['open' => (int) ($load[$u->id] ?? 0)]);

        return $this->data($agents->values()->all());
    }

    /** GET conversations?account_id=&filter=all|mine|free&q=&page= */
    public function conversations(Request $request)
    {
        $me = $request->attributes->get('chat_user');
        $query = $this->scoped($request)->with(['contact.identities', 'account']);

        if ($accountId = (int) $request->query('account_id')) {
            $query->where('account_id', $accountId);
        }

        match ($request->query('filter')) {
            'mine' => $query->where('assigned_to', $me->id),
            'free' => $query->whereNull('assigned_to'),
            default => null,
        };

        if ($q = trim((string) $request->query('q'))) {
            $query->whereHas('contact', fn ($c) => $c->where('name', 'like', '%' . $q . '%')
                ->orWhereHas('identities', fn ($i) => $i->where('external_id', 'like', '%' . $q . '%')));
        }

        $page = $query->orderByDesc('last_message_at')->paginate(30);
        $agents = User::whereIn('id', collect($page->items())->pluck('assigned_to')->filter()->unique())->get()->keyBy('id');

        $lastByConv = \Aero\Hello\Models\Message::whereIn('id', function ($sub) use ($page) {
            $sub->selectRaw('MAX(id)')->from('aero_hello_messages')
                ->whereIn('conversation_id', collect($page->items())->pluck('id'))->groupBy('conversation_id');
        })->get()->keyBy('conversation_id');

        $rows = collect($page->items())->map(function (Conversation $c) use ($agents, $lastByConv) {
            $last = $lastByConv->get($c->id);
            $agent = $agents->get($c->assigned_to);

            return [
                'id'              => $c->id,
                'account_id'      => $c->account_id,
                'status'          => $c->status,
                'unread_count'    => (int) $c->unread_count,
                'last_message_at' => optional($c->last_message_at)->toIso8601String(),
                'contact'         => [
                    'id'    => $c->contact_id,
                    'name'  => $c->contact?->name,
                    'phone' => $c->contact?->identities->first()?->external_id,
                    'avatar_url' => $c->contact?->avatar_url,
                ],
                'last_message'    => $last ? ['body' => $last->body, 'direction' => $last->direction] : null,
                'assigned_to'     => $agent ? AuthController::user($agent) : null,
            ];
        })->all();

        return $this->data($rows, 200, ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]);
    }

    /** GET conversations/{id}/messages?after= — mensajes y eventos internos, en orden cronológico. */
    public function messages(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        $after = $request->query('after');  // ISO-8601 para pedir solo lo nuevo (polling)
        $limit = 100;

        $messages = $conversation->messages()->when($after, fn ($q) => $q->where('created_at', '>', $after))
            ->orderByDesc('created_at')->limit($limit)->get()->reverse()->map(fn ($m) => [
                'kind' => 'message', 'id' => 'm' . $m->id, 'direction' => $m->direction, 'type' => $m->type, 'body' => $m->body,
                'media_url' => $m->media_url, 'status' => $m->status, 'failed_reason' => $m->failed_reason,
                'at' => optional($m->created_at)->toIso8601String(),
            ]);

        $events = ChatEvent::where('conversation_id', $conversation->id)->when($after, fn ($q) => $q->where('created_at', '>', $after))
            ->orderByDesc('id')->limit($limit)->get()->reverse();
        $names = User::whereIn('id', $events->pluck('user_id')->filter())->get()->keyBy('id');

        $events = $events->map(fn ($e) => [
            'kind' => $e->type === 'note' ? 'note' : 'event', 'id' => 'e' . $e->id, 'type' => $e->type, 'body' => $e->body, 'data' => $e->data,
            'by' => ($u = $names->get($e->user_id)) ? AuthController::user($u) : null,
            'at' => optional($e->created_at)->toIso8601String(),
        ]);

        return $this->data($messages->concat($events)->sortBy('at')->values()->all());
    }

    /** POST conversations/{id}/reply {body} */
    public function reply(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        $data = $this->check($request, ['body' => 'required|string|max:4096']);

        try {
            $tx = ApiCredits::charge($this->tenantId($request));
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->error('insufficient_credits', $e->getMessage(), 402);
        }

        try {
            $message = MessageComposer::sendToContact($conversation->account, $conversation->contact_id, $data['body'], ['credit_transaction_id' => $tx]);
        } catch (\Throwable $e) {
            return $this->error('send_failed', $e->getMessage(), 422);
        }

        // Quien responde una conversación libre se la queda.
        $update = ['unread_count' => 0];
        if (!$conversation->assigned_to) {
            $update['assigned_to'] = $request->attributes->get('chat_user')->id;
        }
        $conversation->update($update);

        return $this->data([
            'kind' => 'message', 'id' => 'm' . $message->id, 'direction' => $message->direction, 'type' => $message->type,
            'body' => $message->body, 'status' => $message->status, 'at' => optional($message->created_at)->toIso8601String(),
        ], 202);
    }

    /** POST conversations/{id}/note {body} — solo visible para el equipo. */
    public function note(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        $data = $this->check($request, ['body' => 'required|string|max:4096']);
        $event = $this->log($request, $conversation, 'note', $data['body']);

        return $this->data(['id' => 'e' . $event->id], 201);
    }

    /** POST conversations/{id}/read */
    public function markRead(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        $conversation->update(['unread_count' => 0]);

        return $this->data(['ok' => true]);
    }

    /** POST conversations/{id}/delegate {agent_id|null, note?} — null la deja sin asignar. */
    public function delegate(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        $data = $this->check($request, ['agent_id' => 'nullable|integer', 'note' => 'nullable|string|max:1000']);
        $agent = null;

        if (!empty($data['agent_id'])) {
            $tenant = $request->attributes->get('tenant');
            $agent = User::find($data['agent_id']);

            if (!$agent || !$agent->is_activated || !$tenant->isAccessibleBy($agent)) {
                return $this->error('invalid_agent', 'Ese agente no pertenece a este espacio.', 422);
            }
        }

        $conversation->update(['assigned_to' => $agent?->id]);
        $this->log($request, $conversation, 'delegated', $data['note'] ?? null, [
            'to' => $agent ? AuthController::user($agent) : null,
        ]);

        return $this->data(['assigned_to' => $agent ? AuthController::user($agent) : null]);
    }

    // ------------------------------------------------------------------

    protected function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenant_id');
    }

    protected function accountIds(Request $request)
    {
        return Account::forTenant($this->tenantId($request))->pluck('id');
    }

    /** Aísla por las cuentas del tenant: es lo único que Zernio y wapi comparten. */
    protected function scoped(Request $request)
    {
        return Conversation::whereIn('account_id', $this->accountIds($request));
    }

    protected function find(Request $request, $id): ?Conversation
    {
        return $this->scoped($request)->with('account.profile')->find($id);
    }

    protected function log(Request $request, Conversation $c, string $type, ?string $body = null, array $data = []): ChatEvent
    {
        return ChatEvent::create([
            'tenant_id' => $this->tenantId($request), 'conversation_id' => $c->id,
            'user_id' => $request->attributes->get('chat_user')->id, 'type' => $type, 'body' => $body, 'data' => $data ?: null,
        ]);
    }

    protected function data($data, int $status = 200, array $meta = [])
    {
        return response()->json($meta ? ['data' => $data, 'meta' => $meta] : ['data' => $data], $status);
    }

    protected function error(string $code, string $message, int $status)
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }

    protected function notFound()
    {
        return $this->error('not_found', 'No encontrado.', 404);
    }
}
