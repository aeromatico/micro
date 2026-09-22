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

        $pending = Conversation::whereIn('account_id', $accounts->pluck('id'))->where('unread_count', '>', 0)->get(['id', 'account_id', 'unread_count']);
        $lastOut = $this->lastMessages($pending->pluck('id'))->filter(fn ($m) => $m->direction === 'outbound')->keys();
        $unread = $pending->reject(fn ($c) => $lastOut->contains($c->id))->groupBy('account_id')->map(fn ($g) => $g->sum('unread_count'));

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

        // Los chats archivados solo aparecen en filter=archived; el resto de vistas los oculta.
        $query->where('is_archived', $request->query('filter') === 'archived');

        match ($request->query('filter')) {
            'mine' => $query->where('assigned_to', $me->id),
            'free' => $query->whereNull('assigned_to'),
            // Pendientes: con no leídos y cuyo último mensaje lo escribió el cliente (igual que la insignia de la lista).
            'unread' => $query->where('unread_count', '>', 0)->whereRaw(
                "(select m.direction from aero_hello_messages m where m.conversation_id = {$query->getModel()->getTable()}.id order by m.id desc limit 1) = 'inbound'"
            ),
            default => null,
        };

        if ($q = trim((string) $request->query('q'))) {
            $query->whereHas('contact', fn ($c) => $c->where('name', 'like', '%' . $q . '%')
                ->orWhereHas('identities', fn ($i) => $i->where('external_id', 'like', '%' . $q . '%')));
        }

        // Manda el último mensaje real (entrante o saliente), no last_message_at: varios envíos no lo actualizan.
        $table = $query->getModel()->getTable();
        $page = $query->orderByRaw("coalesce((select max(m.created_at) from aero_hello_messages m where m.conversation_id = {$table}.id), {$table}.last_message_at) desc")
            ->orderByDesc("{$table}.id")->paginate(30);
        $agents = User::whereIn('id', collect($page->items())->pluck('assigned_to')->filter()->unique())->get()->keyBy('id');

        $lastByConv = $this->lastMessages(collect($page->items())->pluck('id'));

        $rows = collect($page->items())->map(fn (Conversation $c) => $this->row($c, $agents->get($c->assigned_to), $lastByConv->get($c->id)))->all();

        return $this->data($rows, 200, ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]);
    }

    /** GET conversations/code/{code} — recupera una conversación por su código público. */
    public function byCode(Request $request, $code)
    {
        $c = $this->scoped($request)->with(['contact.identities', 'account'])->where('code', $code)->first();
        if (!$c) {
            return $this->error('not_found', 'No encontrado.', 404);
        }

        $agent = $c->assigned_to ? User::find($c->assigned_to) : null;

        return $this->data($this->row($c, $agent, $this->lastMessages(collect([$c->id]))->get($c->id)));
    }

    /** POST conversations/new {account_id, phone, body, name?} — abre una conversación con un número nuevo. */
    public function start(Request $request)
    {
        $data = $this->check($request, [
            'account_id' => 'required|integer', 'phone' => 'required|string|max:32',
            'body' => 'required|string|max:4096', 'name' => 'nullable|string|max:120',
        ]);

        $account = Account::forTenant($this->tenantId($request))->enabled()->find($data['account_id']);
        if (!$account) {
            return $this->error('invalid_account', 'Esa cuenta no pertenece a este espacio.', 422);
        }

        $phone = preg_replace('/\D+/', '', $data['phone']);
        if (strlen($phone) < 8 || strlen($phone) > 15) {
            return $this->error('invalid_phone', 'Ingresa el número con código de país, por ejemplo 591 7xxxxxxx.', 422);
        }

        try {
            $tx = ApiCredits::charge($this->tenantId($request));
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->error('insufficient_credits', $e->getMessage(), 402);
        }

        try {
            $message = MessageComposer::send($account, $phone, $data['body'], ['name' => $data['name'] ?? null, 'credit_transaction_id' => $tx]);
        } catch (\Throwable $e) {
            return $this->error('send_failed', $e->getMessage(), 422);
        }

        $conversation = Conversation::with(['contact.identities', 'account'])->find($message->conversation_id);
        $me = $request->attributes->get('chat_user');
        $update = ['unread_count' => 0, 'last_message_at' => now()];
        if (!$conversation->assigned_to) {
            $update['assigned_to'] = $me->id;
        }
        $conversation->update($update);

        return $this->data($this->row($conversation->fresh(['contact.identities']), User::find($conversation->assigned_to), $message), 202);
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
                'media_url' => $m->media_url, 'media_type' => $m->media_type, 'status' => $m->status, 'failed_reason' => $m->failed_reason,
                'quote' => $m->provider_payload['quoted'] ?? null,
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

        $data = $this->check($request, ['body' => 'required|string|max:4096', 'reply_to' => 'nullable|integer']);

        // Citar un mensaje: solo cuentas cuyo driver lo declara (wapi). El id
        // del proveedor sale del propio mensaje; sin él no hay nada que citar.
        $options = [];
        if (!empty($data['reply_to'])) {
            if (!$conversation->account->can('quote_reply')) {
                return $this->error('unsupported', 'Este número no admite citar mensajes.', 422);
            }

            $quoted = $conversation->messages()->find($data['reply_to']);
            if (!$quoted) {
                return $this->error('not_found', 'El mensaje a citar no existe en esta conversación.', 404);
            }
            if (!$quoted->zernio_message_id) {
                return $this->error('not_quotable', 'Ese mensaje aún no tiene identificador del proveedor; inténtalo en unos segundos.', 422);
            }

            $options['provider_payload'] = [
                'reply_to_message_id' => $quoted->zernio_message_id,
                'quoted' => [
                    'id'        => 'm' . $quoted->id,
                    'direction' => $quoted->direction,
                    'body'      => mb_strimwidth((string) ($quoted->body ?: ($quoted->media_type ? '[' . $quoted->media_type . ']' : '')), 0, 140, '…'),
                ],
            ];
        }

        try {
            $tx = ApiCredits::charge($this->tenantId($request));
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->error('insufficient_credits', $e->getMessage(), 402);
        }

        try {
            $message = MessageComposer::sendToContact($conversation->account, $conversation->contact_id, $data['body'], ['credit_transaction_id' => $tx] + $options);
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
            'body' => $message->body, 'status' => $message->status, 'quote' => $options['provider_payload']['quoted'] ?? null,
            'at' => optional($message->created_at)->toIso8601String(),
        ], 202);
    }

    /** POST conversations/{id}/attachment (multipart: file, caption?) — imagen, video, audio o documento. */
    public function attachment(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        if (!$conversation->account->can('media')) {
            return $this->error('unsupported', 'Este número no admite enviar archivos.', 422);
        }

        $upload = $request->file('file');
        if (!$upload || !$upload->isValid()) {
            return $this->error('validation_failed', 'No se recibió el archivo (¿supera el límite del servidor?).', 422);
        }

        if ($upload->getSize() > 16 * 1024 * 1024) {
            return $this->error('validation_failed', 'El archivo pesa más de 16 MB.', 422);
        }

        $caption = mb_substr(trim((string) $request->input('caption')), 0, 1024);
        $mime = strtolower(explode(';', (string) $upload->getMimeType())[0]);
        $name = $upload->getClientOriginalName() ?: 'archivo';
        $path = $upload->getRealPath();
        $tmp = null;

        $kind = match (true) {
            str_starts_with($mime, 'audio/'), $request->boolean('voice') => 'audio',   // el webm de una grabación se detecta a veces como video/webm
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'document',
        };

        // Lo que graba el navegador (webm) no lo reproduce WhatsApp: se pasa a ogg/opus.
        if ($kind === 'audio' && !preg_match('#^audio/(ogg|mpeg|mp3|mp4|aac|x-m4a|amr)#', $mime)) {
            $tmp = tempnam(sys_get_temp_dir(), 'chat') . '.ogg';
            exec('ffmpeg -y -loglevel error -i ' . escapeshellarg($path) . ' -vn -c:a libopus -b:a 32k ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
            if ($code !== 0 || !is_file($tmp)) {
                @unlink($tmp);
                return $this->error('convert_failed', 'No se pudo procesar el audio.', 422);
            }
            $path = $tmp;
            $name = 'audio-' . now()->format('Ymd-His') . '.ogg';
        }

        try {
            $file = new \System\Models\File;
            $file->fromFile($path, $name);
            $file->is_public = true;
            $file->save();
        } finally {
            if ($tmp) {
                @unlink($tmp);
            }
        }

        $url = $file->getPath();

        try {
            $tx = ApiCredits::charge($this->tenantId($request));
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->error('insufficient_credits', $e->getMessage(), 402);
        }

        try {
            $message = MessageComposer::sendToContact($conversation->account, $conversation->contact_id, $caption, [
                'media_url' => $url, 'media_type' => $kind, 'type' => $kind === 'document' ? 'document' : $kind, 'credit_transaction_id' => $tx,
            ]);
        } catch (\Throwable $e) {
            return $this->error('send_failed', $e->getMessage(), 422);
        }

        $update = ['unread_count' => 0];
        if (!$conversation->assigned_to) {
            $update['assigned_to'] = $request->attributes->get('chat_user')->id;
        }
        $conversation->update($update);

        return $this->data([
            'kind' => 'message', 'id' => 'm' . $message->id, 'direction' => 'outbound', 'type' => $message->type, 'body' => $message->body,
            'media_url' => $url, 'media_type' => $kind, 'file_name' => $name, 'status' => $message->status, 'at' => optional($message->created_at)->toIso8601String(),
        ], 202);
    }

    /** POST conversations/{id}/poll {question, options[2-12], multiple?} — encuesta de WhatsApp Web. */
    public function poll(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        if (!$conversation->account->can('poll')) {
            return $this->error('unsupported', 'Este número no admite encuestas (solo WhatsApp Web).', 422);
        }

        $data = $this->check($request, ['question' => 'required|string|max:255', 'options' => 'required|array|min:2|max:12', 'options.*' => 'nullable|string|max:100', 'multiple' => 'nullable|boolean']);

        try {
            $structured = \Aero\Hello\Classes\StructuredMessage::build('poll', ['poll_name' => $data['question'], 'poll_options' => $data['options'], 'poll_multiple' => $data['multiple'] ?? false]);
        } catch (\InvalidArgumentException $e) {
            return $this->error('validation_failed', $e->getMessage(), 422);
        }

        try {
            $tx = ApiCredits::charge($this->tenantId($request));
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->error('insufficient_credits', $e->getMessage(), 402);
        }

        try {
            $message = MessageComposer::sendToContact($conversation->account, $conversation->contact_id, $structured['body'],
                ['type' => 'poll', 'provider_payload' => $structured['payload'], 'credit_transaction_id' => $tx]);
        } catch (\Throwable $e) {
            return $this->error('send_failed', $e->getMessage(), 422);
        }

        $update = ['unread_count' => 0];
        if (!$conversation->assigned_to) {
            $update['assigned_to'] = $request->attributes->get('chat_user')->id;
        }
        $conversation->update($update);

        return $this->data([
            'kind' => 'message', 'id' => 'm' . $message->id, 'direction' => 'outbound', 'type' => 'poll',
            'body' => $message->body, 'status' => $message->status, 'at' => optional($message->created_at)->toIso8601String(),
        ], 202);
    }

    /** POST conversations/{id}/location {latitude, longitude, accuracy?} — ubicación del operador. */
    public function location(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        if (!$conversation->account->can('location')) {
            return $this->error('unsupported', 'Este número no admite enviar ubicaciones (solo WhatsApp Web).', 422);
        }

        $data = $this->check($request, ['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180']);

        try {
            $structured = \Aero\Hello\Classes\StructuredMessage::build('location', $data);
        } catch (\InvalidArgumentException $e) {
            return $this->error('validation_failed', $e->getMessage(), 422);
        }

        try {
            $tx = ApiCredits::charge($this->tenantId($request));
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->error('insufficient_credits', $e->getMessage(), 402);
        }

        try {
            $message = MessageComposer::sendToContact($conversation->account, $conversation->contact_id, $structured['body'],
                ['type' => 'location', 'provider_payload' => $structured['payload'], 'credit_transaction_id' => $tx]);
        } catch (\Throwable $e) {
            return $this->error('send_failed', $e->getMessage(), 422);
        }

        $update = ['unread_count' => 0];
        if (!$conversation->assigned_to) {
            $update['assigned_to'] = $request->attributes->get('chat_user')->id;
        }
        $conversation->update($update);

        return $this->data([
            'kind' => 'message', 'id' => 'm' . $message->id, 'direction' => 'outbound', 'type' => 'location',
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

    /** POST conversations/{id}/archive {archived: bool} — archivar silencia; desarchivar reactiva. */
    public function archive(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        $data = $this->check($request, ['archived' => 'required|boolean']);
        $conversation->update(['is_archived' => $data['archived'], 'is_muted' => $data['archived']]);

        return $this->data(['is_archived' => (bool) $conversation->is_archived, 'is_muted' => (bool) $conversation->is_muted]);
    }

    /** POST conversations/{id}/mute {muted: bool} — no aplica si el chat está archivado (ya está silenciado). */
    public function mute(Request $request, $id)
    {
        $conversation = $this->find($request, $id);
        if (!$conversation) {
            return $this->notFound();
        }

        if ($conversation->is_archived) {
            return $this->error('archived', 'Este chat está archivado: se reactiva al desarchivarlo.', 422);
        }

        $data = $this->check($request, ['muted' => 'required|boolean']);
        $conversation->update(['is_muted' => $data['muted']]);

        return $this->data(['is_muted' => (bool) $conversation->is_muted]);
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

    /** Último mensaje de cada conversación, indexado por conversation_id. */
    protected function lastMessages($conversationIds)
    {
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        return \Aero\Hello\Models\Message::whereIn('id', function ($sub) use ($conversationIds) {
            $sub->selectRaw('MAX(id)')->from('aero_hello_messages')->whereIn('conversation_id', $conversationIds)->groupBy('conversation_id');
        })->get()->keyBy('conversation_id');
    }

    /** Fila de la lista. Solo cuenta como no leído si lo último lo escribió el cliente. */
    protected function row(Conversation $c, ?User $agent, $last): array
    {
        return [
            'id'              => $c->id,
            'code'            => $c->code,
            'account_id'      => $c->account_id,
            'status'          => $c->status,
            'is_archived'     => (bool) $c->is_archived,
            'is_muted'        => (bool) $c->is_muted,
            'unread_count'    => $last && $last->direction === 'outbound' ? 0 : (int) $c->unread_count,
            'last_message_at' => optional($last?->created_at ?? $c->last_message_at)->toIso8601String(),
            'contact'         => [
                'id'    => $c->contact_id,
                'name'  => $c->contact?->name,
                'phone' => $c->contact?->identities->first()?->external_id,
                'avatar_url' => $c->contact?->avatar_url,
            ],
            'last_message'    => $last ? ['body' => $last->body, 'direction' => $last->direction, 'media_type' => $last->media_type, 'type' => $last->type] : null,
            'assigned_to'     => $agent ? AuthController::user($agent) : null,
        ];
    }

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
