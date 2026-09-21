<?php namespace Aero\Crm\Components;

use Aero\Crm\Models\Department;
use Aero\Crm\Models\Ticket;
use Aero\Crm\Models\TicketReply;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

/**
 * Portal de soporte del micrositio del tenant. A diferencia de la mesa de
 * ayuda del panel (agentes), aquí el solicitante es un cliente: un usuario de
 * rainlab:user o un invitado con enlace privado por token.
 *
 * Uso: [supportPortal] mode = "index|show|create", slug = "{{ :id }}"
 */
class SupportPortal extends ComponentBase
{
    public ?Tenant $tenant = null;
    public $user = null;
    public string $mode = 'index';

    public array $tickets = [];
    public ?Ticket $ticket = null;
    public array $replies = [];
    public array $departments = [];
    public bool $canCreate = false;
    public string $guestToken = '';
    public string $statusLabel = '';

    public function componentDetails(): array
    {
        return [
            'name'        => 'CRM Soporte (sitio)',
            'description' => 'Portal de tickets del micrositio para clientes e invitados.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'mode' => ['title' => 'Modo', 'type' => 'dropdown', 'default' => 'index',
                'options' => ['index' => 'Listado', 'show' => 'Ticket', 'create' => 'Nuevo ticket']],
            'slug' => ['title' => 'Id del ticket', 'type' => 'string', 'default' => '{{ :id }}'],
            'base' => ['title' => 'Ruta base', 'type' => 'string', 'default' => 'soporte'],
        ];
    }

    public function onRun()
    {
        $this->tenant = Tenant::resolveFromDomain(request()->getHost());
        if (!$this->tenant) {
            return $this->controller->run('404');
        }

        $this->user = \Auth::getUser();
        $this->mode = (string) $this->property('mode');

        switch ($this->mode) {
            case 'create':
                return $this->loadCreate();
            case 'show':
                return $this->loadShow();
            default:
                return $this->loadIndex();
        }
    }

    protected function base(): string
    {
        return trim((string) $this->property('base'), '/') ?: 'soporte';
    }

    /**
     * Los handlers AJAX no deben depender de que onRun ya haya corrido:
     * resuelven tenant y usuario por su cuenta.
     */
    protected function bootContext(): bool
    {
        if (!$this->tenant) {
            $this->tenant = Tenant::resolveFromDomain(request()->getHost());
        }
        if (!$this->user) {
            $this->user = \Auth::getUser();
        }

        return (bool) $this->tenant;
    }

    protected function baseQuery()
    {
        return Ticket::where('tenant_id', $this->tenant->id);
    }

    protected function loadIndex(): void
    {
        if (!$this->user) {
            return;
        }

        $this->tickets = $this->baseQuery()
            ->forFrontendUser($this->user)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Ticket $t) => $this->ticketRow($t))
            ->all();
    }

    protected function loadCreate(): void
    {
        $this->departments = Department::active()
            ->inScope($this->tenant->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $this->canCreate = count($this->departments) > 0;
    }

    protected function loadShow()
    {
        $ticket = $this->findAccessibleTicket();
        if (!$ticket) {
            return $this->controller->run('404');
        }

        // Al iniciar sesión, un ticket dejado como invitado con el mismo correo
        // se reclama para la cuenta (deja de mostrar el token).
        if ($this->user && !$ticket->frontend_user_id) {
            $ticket->frontend_user_id = $this->user->id;
            $ticket->save();
        }

        // El invitado conserva su token en sesión para recargar sin query string.
        if (!$this->user && $this->guestToken) {
            session()->put('support_tickets.' . $ticket->id, $this->guestToken);
        }

        $this->ticket = $ticket;
        $this->statusLabel = Ticket::statusOptions()[$ticket->status] ?? $ticket->status;
        $this->replies = $this->replyRows($ticket);
    }

    protected function findAccessibleTicket(): ?Ticket
    {
        $id = (int) $this->property('slug');
        if (!$id) {
            return null;
        }

        $ticket = $this->baseQuery()->find($id);
        if (!$ticket) {
            return null;
        }

        $this->guestToken = trim((string) input('token', ''))
            ?: (string) session()->get('support_tickets.' . $id, '');

        return $ticket->isAccessibleBy($this->user, $this->guestToken) ? $ticket : null;
    }

    protected function ticketRow(Ticket $t): array
    {
        return [
            'id'       => $t->id,
            'number'   => $t->number,
            'subject'  => $t->subject,
            'status'   => $t->status,
            'status_label' => Ticket::statusOptions()[$t->status] ?? $t->status,
            'priority' => $t->priority,
            'url'      => $this->ticketUrl($t),
            'updated_at' => $t->updated_at,
        ];
    }

    protected function replyRows(Ticket $t): array
    {
        return $t->replies()
            ->where('is_internal', false)
            ->with(['user', 'frontendUser'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketReply $r) => [
                'id'         => $r->id,
                'author'     => $r->author_label,
                'is_customer' => $r->author_type === TicketReply::CUSTOMER,
                'body'       => $r->body,
                'created_at' => $r->created_at,
            ])
            ->all();
    }

    public function ticketUrl(Ticket $t): string
    {
        $url = url($this->base() . '/ticket/' . $t->id);
        if (!$this->user && $t->access_token) {
            $url .= '?token=' . urlencode($t->access_token);
        }

        return $url;
    }

    /**
     * Crea un ticket desde el micrositio. Un invitado recibe un token para
     * seguirlo; un usuario logueado queda enlazado a su cuenta.
     */
    public function onCreate()
    {
        if (!$this->bootContext()) {
            return ['#support-form-error' => $this->errorBox('No pudimos identificar el sitio.')];
        }

        $data = post();
        $guest = !$this->user;

        $validator = Validator::make($data, [
            'subject'       => 'required|min:4|max:255',
            'description'   => 'required|min:10|max:5000',
            'department_id' => 'required|integer',
            'name'          => $guest ? 'required|min:2|max:120' : 'nullable|max:120',
            'email'         => $guest ? 'required|email|max:190' : 'nullable|email|max:190',
            'phone'         => 'nullable|max:30',
        ], [
            'subject.required'       => 'Escribí un asunto.',
            'description.required'   => 'Contanos el detalle del problema.',
            'description.min'        => 'El detalle es muy corto.',
            'department_id.required' => 'Elegí un área.',
            'name.required'          => 'Tu nombre es obligatorio.',
            'email.required'         => 'Tu correo es obligatorio.',
            'email.email'            => 'El correo no es válido.',
        ]);

        if ($validator->fails()) {
            return ['#support-form-error' => $this->errorBox($validator->errors()->first())];
        }

        $key = 'support-ticket:' . request()->ip() . ':' . $this->tenant->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return ['#support-form-error' => $this->errorBox("Demasiados intentos. Probá en {$seconds}s.")];
        }
        RateLimiter::hit($key, 3600);

        $department = Department::active()
            ->inScope($this->tenant->id)
            ->find((int) $data['department_id']);

        if (!$department) {
            return ['#support-form-error' => $this->errorBox('El área elegida no está disponible.')];
        }

        $ticket = new Ticket();
        $ticket->tenant_id = $this->tenant->id;
        $ticket->department_id = $department->id;
        $ticket->subject = trim($data['subject']);
        $ticket->description = trim($data['description']);
        $ticket->status = Ticket::OPEN;
        $ticket->priority = 'normal';
        $ticket->source = 'web';
        $ticket->requester_name = $this->user ? $this->user->full_name : trim($data['name']);
        $ticket->requester_email = $this->user ? $this->user->email : trim($data['email']);
        $ticket->requester_phone = trim((string) ($data['phone'] ?? '')) ?: null;

        if ($this->user) {
            $ticket->frontend_user_id = $this->user->id;
        }
        else {
            $ticket->access_token = $ticket->generateAccessToken();
        }

        $ticket->save();

        if (!$this->user) {
            session()->put('support_tickets.' . $ticket->id, $ticket->access_token);
        }

        \Event::fire('aero.crm.support.ticketCreated', [$ticket]);

        return Redirect::to($this->ticketUrl($ticket));
    }

    /**
     * Respuesta del cliente. Un agente no usa este handler (responde desde el
     * panel); aquí siempre es author_type = customer.
     */
    public function onReply()
    {
        if (!$this->bootContext()) {
            return ['#support-thread' => '<div class="text-sm text-red-400">No pudimos identificar el sitio.</div>'];
        }

        $ticket = $this->findAccessibleTicket();
        if (!$ticket) {
            return ['#support-thread' => '<div class="text-sm text-red-400">No encontramos el ticket.</div>'];
        }

        $body = trim((string) post('body'));
        if (mb_strlen($body) < 2) {
            return ['#support-reply-error' => $this->errorBox('Escribí tu mensaje.')];
        }
        if (mb_strlen($body) > 5000) {
            return ['#support-reply-error' => $this->errorBox('El mensaje es demasiado largo.')];
        }

        $key = 'support-reply:' . request()->ip() . ':' . $ticket->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $seconds = RateLimiter::availableIn($key);
            return ['#support-reply-error' => $this->errorBox("Demasiados mensajes. Probá en {$seconds}s.")];
        }
        RateLimiter::hit($key, 3600);

        TicketReply::create([
            'ticket_id'        => $ticket->id,
            'author_type'      => TicketReply::CUSTOMER,
            'frontend_user_id' => $this->user?->id,
            'author_name'      => $this->user ? $this->user->full_name : ($ticket->requester_name ?: 'Cliente'),
            'body'             => $body,
            'is_internal'      => false,
        ]);

        $ticket->last_customer_reply_at = now();
        $ticket->unread_for_agent = true;
        if (in_array($ticket->status, [Ticket::RESOLVED, Ticket::CLOSED], true)) {
            $ticket->status = Ticket::OPEN;
        }
        $ticket->save();

        $this->ticket = $ticket->fresh();
        $this->statusLabel = Ticket::statusOptions()[$this->ticket->status] ?? $this->ticket->status;
        $this->replies = $this->replyRows($this->ticket);

        \Event::fire('aero.crm.support.ticketReplied', [$this->ticket]);

        return ['#support-thread' => $this->renderPartial('@ticket')];
    }

    protected function errorBox(string $message): string
    {
        return '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-400">' . e($message) . '</div>';
    }
}
