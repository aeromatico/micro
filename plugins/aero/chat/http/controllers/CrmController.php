<?php namespace Aero\Chat\Http\Controllers;

use Aero\Chat\Models\ChatEvent;
use Aero\Hello\Classes\MessageComposer;
use Aero\Hello\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Panel CRM/Pay de una conversación: listas, ticket, lead/negocio y cobros.
 *
 * CRM y Pay son opcionales: cada bloque se omite si su plugin no está. El
 * contacto del chat se enlaza con el del CRM por `hello_contact_id` (el mismo
 * enlace que mantiene Aero\Crm\Classes\HelloSync); solo los chats de WhatsApp
 * se reflejan en el CRM porque el enlace es el teléfono.
 */
class CrmController extends Controller
{
    use \Aero\Chat\Classes\ValidatesJson;

    /** GET conversations/{id}/crm */
    public function show(Request $request, $id)
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return $err;
        }

        return $this->ok($this->panel($request, $conv, $this->crmContact($request, $conv, false)));
    }

    /** POST conversations/{id}/crm/lists {list_id, on} */
    public function list(Request $request, $id)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        $data = $this->check($request, ['list_id' => 'required|integer', 'on' => 'required|boolean']);
        $list = \Aero\Crm\Models\ContactList::forTenant($this->tenantId($request))->find($data['list_id']);
        if (!$list) {
            return $this->fail('not_found', 'Lista no encontrada.', 404);
        }

        $data['on'] ? $contact->contactLists()->syncWithoutDetaching([$list->id]) : $contact->contactLists()->detach($list->id);

        return $this->ok($this->panel($request, $conv, $contact));
    }

    /** POST conversations/{id}/crm/ticket {department_id, subject?, priority?} */
    public function createTicket(Request $request, $id)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        $tenantId = $this->tenantId($request);
        $data = $this->check($request, [
            'department_id' => 'required|integer', 'subject' => 'nullable|string|max:255', 'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $dept = \Aero\Crm\Models\Department::active()->inScope($tenantId)->find($data['department_id']);
        if (!$dept) {
            return $this->fail('invalid_department', 'Ese departamento no existe.', 422);
        }

        $me = $request->attributes->get('chat_user');
        $last = $conv->messages()->where('direction', 'inbound')->latest('id')->value('body');

        $ticket = \Aero\Crm\Models\Ticket::create([
            'tenant_id'       => $tenantId,
            'subject'         => (trim((string) ($data['subject'] ?? '')) ?: 'Chat con ' . $contact->full_name),
            'description'     => $last,
            'department_id'   => $dept->id,
            'contact_id'      => $contact->id,
            'requester_name'  => $contact->full_name,
            'requester_phone' => $contact->phone,
            'assigned_to'     => $conv->assigned_to ?: $me->id,
            'priority'        => $data['priority'] ?? 'normal',
            'source'          => 'chat',
        ]);

        $this->log($request, $conv, 'ticket', 'Ticket ' . $ticket->number . ' creado en ' . $dept->name, ['ticket_id' => $ticket->id]);

        return $this->ok($this->panel($request, $conv, $contact), 201);
    }

    /** POST conversations/{id}/crm/ticket/{ticketId} {status} */
    public function updateTicket(Request $request, $id, $ticketId)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        $data = $this->check($request, ['status' => 'required|in:open,pending,resolved,closed']);
        $ticket = \Aero\Crm\Models\Ticket::where('tenant_id', $this->tenantId($request))->where('contact_id', $contact->id)->find($ticketId);
        if (!$ticket) {
            return $this->fail('not_found', 'Ticket no encontrado.', 404);
        }

        $ticket->update(['status' => $data['status']]);
        $this->log($request, $conv, 'ticket', 'Ticket ' . $ticket->number . ' → ' . \Aero\Crm\Models\Ticket::statusOptions()[$data['status']], ['ticket_id' => $ticket->id]);

        return $this->ok($this->panel($request, $conv, $contact));
    }

    /** POST conversations/{id}/crm/lead {status?} — crea el lead si no existe; con status lo actualiza. */
    public function lead(Request $request, $id)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        $data = $this->check($request, ['status' => 'nullable|in:new,contacted,qualified,disqualified']);
        $lead = $this->findLead($request, $contact);

        if (!$lead) {
            $lead = \Aero\Crm\Models\Lead::create([
                'tenant_id' => $this->tenantId($request), 'name' => $contact->full_name, 'phone' => $contact->phone,
                'source' => 'whatsapp', 'status' => $data['status'] ?? 'new', 'owner_id' => $request->attributes->get('chat_user')->id,
            ]);
            $this->log($request, $conv, 'lead', 'Lead creado desde el chat', ['lead_id' => $lead->id]);
        } elseif (!empty($data['status'])) {
            $lead->update(['status' => $data['status']]);
        }

        return $this->ok($this->panel($request, $conv, $contact));
    }

    /** POST conversations/{id}/crm/lead/convert — lead → negocio en la primera etapa. */
    public function convertLead(Request $request, $id)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        $lead = $this->findLead($request, $contact);
        if (!$lead) {
            return $this->fail('no_lead', 'Primero crea el lead.', 422);
        }

        // No se usa Lead::convert(): crearía un contacto duplicado. Acá el
        // negocio cuelga del contacto que ya está en este chat.
        if (!$lead->converted_deal_id) {
            $pipeline = \Aero\Crm\Models\Pipeline::seedDefaultForTenant($lead->tenant_id);
            $first = $pipeline->stages()->orderBy('sort_order')->first();
            $deal = \Aero\Crm\Models\Deal::create([
                'tenant_id' => $lead->tenant_id, 'pipeline_id' => $pipeline->id, 'stage_id' => $first->id,
                'contact_id' => $contact->id, 'company_id' => $contact->company_id, 'title' => $lead->name,
                'owner_id' => $lead->owner_id ?: $request->attributes->get('chat_user')->id, 'in_pipeline' => true,
            ]);
            $lead->update(['converted_contact_id' => $contact->id, 'converted_deal_id' => $deal->id, 'status' => 'qualified']);
        }

        $this->log($request, $conv, 'lead', 'Lead convertido en negocio', ['lead_id' => $lead->id]);

        return $this->ok($this->panel($request, $conv, $contact));
    }

    /** POST conversations/{id}/crm/deal/create {title?} — negocio directo, sin pasar por lead. */
    public function createDeal(Request $request, $id)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        $data = $this->check($request, ['title' => 'nullable|string|max:255']);
        $tenantId = $this->tenantId($request);
        $me = $request->attributes->get('chat_user');

        $open = \Aero\Crm\Models\Deal::forTenant($tenantId)->where('contact_id', $contact->id)->where('status', 'open')->exists();
        if ($open) {
            return $this->fail('deal_exists', 'Este contacto ya tiene un negocio abierto.', 422);
        }

        $pipeline = \Aero\Crm\Models\Pipeline::seedDefaultForTenant($tenantId);
        $first = $pipeline->stages()->orderBy('sort_order')->first();
        $deal = \Aero\Crm\Models\Deal::create([
            'tenant_id' => $tenantId, 'pipeline_id' => $pipeline->id, 'stage_id' => $first->id,
            'contact_id' => $contact->id, 'company_id' => $contact->company_id,
            'title' => trim((string) ($data['title'] ?? '')) ?: $contact->full_name,
            'owner_id' => $me->id,
        ]);

        // Si el contacto tenía un lead sin convertir, queda enlazado a este negocio.
        if (($lead = $this->findLead($request, $contact)) && !$lead->converted_deal_id) {
            $lead->update(['converted_contact_id' => $contact->id, 'converted_deal_id' => $deal->id, 'status' => 'qualified']);
        }

        $this->log($request, $conv, 'deal', 'Negocio creado desde el chat', ['deal_id' => $deal->id]);

        return $this->ok($this->panel($request, $conv, $contact), 201);
    }

    /** POST conversations/{id}/crm/deal {stage_id} */
    public function moveDeal(Request $request, $id)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        $data = $this->check($request, ['stage_id' => 'required|integer']);
        $tenantId = $this->tenantId($request);
        $deal = $this->findDeal($tenantId, $contact);
        $stage = $deal ? \Aero\Crm\Models\PipelineStage::forTenant($tenantId)->where('pipeline_id', $deal->pipeline_id)->find($data['stage_id']) : null;

        if (!$deal || !$stage) {
            return $this->fail('not_found', 'Negocio o etapa no encontrados.', 404);
        }

        $deal->update(['stage_id' => $stage->id, 'status' => $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open')]);
        $this->log($request, $conv, 'deal', 'Negocio movido a ' . $stage->name, ['deal_id' => $deal->id]);

        return $this->ok($this->panel($request, $conv, $contact));
    }

    /** POST conversations/{id}/crm/collections/{itemId}/qr — emite el QR de Pay y lo manda al chat. */
    public function sendQr(Request $request, $id, $itemId)
    {
        [$conv, $err, $contact] = $this->ready($request, $id);
        if ($err) {
            return $err;
        }

        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return $this->fail('pay_unavailable', 'Cobros con QR no está disponible.', 422);
        }

        $item = $this->collectionsFor($this->tenantId($request), $contact)->find($itemId);
        if (!$item || $item->status !== 'pending') {
            return $this->fail('not_found', 'Cobro no encontrado o ya resuelto.', 404);
        }

        $qr = (new \Aero\Crm\Classes\Collections\CollectionQrIssuer())->issueFor($item);
        if (!$qr || !$qr->qr_image) {
            return $this->fail('no_qr', 'No se pudo generar el QR. Revisa la cuenta bancaria de cobranzas en el CRM.', 422);
        }

        $amount = number_format((float) $item->amount, 2, ',', '.') . ' ' . ($item->currency ?: 'BOB');
        $body = $item->concept . "\nMonto: " . $amount . "\nPaga escaneando este QR desde tu app bancaria.";

        try {
            $message = MessageComposer::sendToContact($conv->account, $conv->contact_id, $body, [
                'media_url' => url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image'), 'media_type' => 'image',
            ]);
        } catch (\Throwable $e) {
            return $this->fail('send_failed', $e->getMessage(), 422);
        }

        $this->log($request, $conv, 'charge', 'Cobro enviado por QR: ' . $item->concept . ' · ' . $amount, ['collection_item_id' => $item->id]);

        return $this->ok(['message_id' => $message->id, 'panel' => $this->panel($request, $conv, $contact)], 202);
    }

    // ------------------------------------------------------------------

    protected function panel(Request $request, Conversation $conv, $contact): array
    {
        $tenantId = $this->tenantId($request);
        $crmOn = class_exists(\Aero\Crm\Models\Contact::class) && \Aero\Crm\Models\CrmSettings::where('tenant_id', $tenantId)->value('is_enabled');

        $out = ['crm' => (bool) $crmOn, 'whatsapp' => $conv->account?->platform === 'whatsapp', 'contact' => null];
        if (!$crmOn) {
            return $out;
        }

        // Los bloques que no dependen del contacto se envían siempre, para que
        // la PWA pueda ofrecer "crear" aunque el contacto aún no esté en el CRM.
        $out['departments'] = \Aero\Crm\Models\Department::active()->inScope($tenantId)->orderBy('name')->get(['id', 'name'])->all();
        $out['all_lists'] = \Aero\Crm\Models\ContactList::forTenant($tenantId)->orderBy('name')->get(['id', 'name', 'color'])->all();
        $out['pay'] = class_exists(\Aero\Pay\Models\QrCode::class);

        if (!$contact) {
            return $out;
        }

        $out['contact'] = ['id' => $contact->id, 'name' => $contact->full_name, 'phone' => $contact->phone, 'email' => $contact->email];
        $out['list_ids'] = $contact->contactLists()->pluck('aero_crm_contact_lists.id')->all();

        $t = \Aero\Crm\Models\Ticket::with(['department', 'assignee'])->where('tenant_id', $tenantId)->where('contact_id', $contact->id)
            ->orderByRaw("status IN ('open','pending') DESC")->latest('id')->first();
        $out['ticket'] = $t ? [
            'id' => $t->id, 'number' => $t->number, 'subject' => $t->subject, 'status' => $t->status, 'priority' => $t->priority,
            'department' => $t->department?->name, 'assignee' => $t->assignee ? AuthController::user($t->assignee) : null,
        ] : null;

        $lead = $this->findLead($request, $contact);
        $out['lead'] = $lead ? ['id' => $lead->id, 'status' => $lead->status, 'converted' => (bool) $lead->converted_deal_id] : null;

        $deal = $this->findDeal($tenantId, $contact);
        $out['deal'] = $deal ? ['id' => $deal->id, 'title' => $deal->title, 'value' => (float) $deal->value, 'currency' => $deal->currency, 'stage_id' => $deal->stage_id, 'status' => $deal->status] : null;
        $out['stages'] = $deal
            ? \Aero\Crm\Models\PipelineStage::forTenant($tenantId)->where('pipeline_id', $deal->pipeline_id)->orderBy('sort_order')->get(['id', 'name', 'is_won', 'is_lost'])->all()
            : [];

        $out['collections'] = $this->collectionsFor($tenantId, $contact)->where('status', 'pending')->orderBy('due_date')->get()->map(fn ($i) => [
            'id' => $i->id, 'concept' => $i->concept, 'amount' => (float) $i->amount, 'currency' => $i->currency ?: 'BOB',
            'due_date' => optional($i->due_date)->toDateString(), 'overdue' => $i->isOverdue(),
        ])->all();

        return $out;
    }

    protected function findLead(Request $request, $contact)
    {
        return \Aero\Crm\Models\Lead::forTenant($this->tenantId($request))
            ->where(fn ($q) => $q->where('converted_contact_id', $contact->id)->orWhere(fn ($p) => $contact->phone ? $p->where('phone', $contact->phone) : $p->whereRaw('1 = 0')))
            ->latest('id')->first();
    }

    protected function findDeal(int $tenantId, $contact)
    {
        return \Aero\Crm\Models\Deal::forTenant($tenantId)->where('contact_id', $contact->id)->latest('id')->first();
    }

    protected function collectionsFor(int $tenantId, $contact)
    {
        return \Aero\Crm\Models\CollectionItem::forTenant($tenantId)->where(fn ($q) => $q
            ->where('contact_id', $contact->id)->orWhereHas('recipients', fn ($r) => $r->where('aero_crm_contacts.id', $contact->id)));
    }

    /** Contacto del CRM enlazado al del chat; con $create lo crea a partir del teléfono de WhatsApp. */
    protected function crmContact(Request $request, Conversation $conv, bool $create)
    {
        if (!class_exists(\Aero\Crm\Models\Contact::class) || !$conv->contact) {
            return null;
        }

        $tenantId = $this->tenantId($request);
        $linked = \Aero\Crm\Models\Contact::where('tenant_id', $tenantId)->where('hello_contact_id', $conv->contact_id)->first();

        if ($linked || !$create || $conv->account?->platform !== 'whatsapp') {
            return $linked;
        }

        $external = $conv->contact->identities()->where('platform', 'whatsapp')->value('external_id');

        return $external ? \Aero\Crm\Classes\HelloSync::mirror($conv->contact, $external) : null;
    }

    /** @return array [Conversation|null, JsonResponse|null, CrmContact|null] */
    protected function ready(Request $request, $id): array
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return [null, $err, null];
        }

        $on = class_exists(\Aero\Crm\Models\Contact::class) && \Aero\Crm\Models\CrmSettings::where('tenant_id', $this->tenantId($request))->value('is_enabled');
        if (!$on) {
            return [null, $this->fail('crm_disabled', 'El CRM no está activado para este espacio.', 422), null];
        }

        $contact = $this->crmContact($request, $conv, true);
        if (!$contact) {
            return [null, $this->fail('no_contact', 'El CRM solo aplica a chats de WhatsApp con teléfono.', 422), null];
        }

        return [$conv, null, $contact];
    }

    protected function conversation(Request $request, $id): array
    {
        $ids = \Aero\Hello\Models\Account::forTenant($this->tenantId($request))->pluck('id');
        $conv = Conversation::whereIn('account_id', $ids)->with(['account', 'contact'])->find($id);

        return [$conv, $conv ? null : $this->fail('not_found', 'No encontrado.', 404)];
    }

    protected function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenant_id');
    }

    protected function log(Request $request, Conversation $c, string $type, string $body, array $data = []): void
    {
        ChatEvent::create(['tenant_id' => $this->tenantId($request), 'conversation_id' => $c->id, 'user_id' => $request->attributes->get('chat_user')->id, 'type' => $type, 'body' => $body, 'data' => $data ?: null]);
    }

    protected function ok($data, int $status = 200)
    {
        return response()->json(['data' => $data], $status);
    }

    protected function fail(string $code, string $message, int $status)
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
