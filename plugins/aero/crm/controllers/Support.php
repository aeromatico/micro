<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Classes\TenantUsers;
use Aero\Crm\Models\Department;
use Aero\Crm\Models\Ticket;
use Aero\Crm\Models\TicketReply;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;
use Redirect;

/**
 * Soporte de la PLATAFORMA visto por un tenant. Distinto de CRM → Tickets, que
 * es el sistema de tickets propio del tenant para sus clientes: aquí el tenant
 * es el solicitante y el ticket cae en la mesa de ayuda de la plataforma
 * (tenant_id NULL) marcado con origin_tenant_id.
 *
 * Sin permiso propio a propósito: cualquier usuario del tenant puede pedir
 * ayuda; el aislamiento lo da el filtro por origin_tenant_id.
 */
class Support extends Controller
{
    public $requiredPermissions = [];

    public function __construct()
    {
        parent::__construct();
        // Contexto propio (sin ítem registrado) para no mostrar el menú lateral del CRM,
        // que es del tenant; esta pantalla es solo el soporte de la plataforma.
        BackendMenu::setContext('Aero.Crm', 'support');
        $this->pageTitle = 'Soporte';
    }

    protected function tenantId(): int
    {
        $id = TenantUsers::currentTenantId();
        abort_unless($id, 403, 'Esta pantalla es para tenants; la mesa de ayuda de la plataforma está en CRM → Tickets.');

        return $id;
    }

    protected function mine()
    {
        return Ticket::whereNull('tenant_id')->where('origin_tenant_id', $this->tenantId());
    }

    public function index()
    {
        $this->vars['tickets'] = $this->mine()->with('department')->orderByDesc('created_at')->limit(100)->get();
    }

    public function create()
    {
        $this->tenantId();
        $this->vars['departments'] = Department::inScope(null)->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id');
    }

    public function view($id = null)
    {
        $this->vars['ticket'] = $this->mine()->findOrFail((int) $id);
    }

    public function onCreate()
    {
        $tenantId = $this->tenantId();
        $user = BackendAuth::getUser();

        $ticket = Ticket::create([
            'tenant_id'        => null,
            'origin_tenant_id' => $tenantId,
            'subject'          => trim((string) post('subject')),
            'description'      => trim((string) post('description')),
            'department_id'    => (int) post('department_id'),
            'priority'         => in_array(post('priority'), ['low', 'normal', 'high', 'urgent'], true) ? post('priority') : 'normal',
            'source'           => 'tenant',
            'requester_name'   => trim($user->first_name . ' ' . $user->last_name) ?: $user->login,
            'requester_email'  => $user->email,
            'unread_for_agent' => true,
        ]);

        Flash::success('Ticket #' . $ticket->number . ' enviado al equipo de soporte.');

        return Redirect::to(\Backend::url('aero/crm/support/view/' . $ticket->id));
    }

    public function onReply($id = null)
    {
        $ticket = $this->mine()->findOrFail((int) $id);
        $body = trim((string) post('body'));

        if ($body === '') {
            Flash::error('Escribe un mensaje.');
            return;
        }

        $user = BackendAuth::getUser();

        TicketReply::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $user->id,
            'author_type' => TicketReply::CUSTOMER,
            'author_name' => (trim($user->first_name . ' ' . $user->last_name) ?: $user->login) . ' · ' . $ticket->originTenant?->name,
            'body'        => $body,
        ]);

        // Una respuesta reabre el ticket y avisa a los agentes de la plataforma.
        $ticket->unread_for_agent = true;
        $ticket->last_customer_reply_at = now();
        if (in_array($ticket->status, [Ticket::RESOLVED, Ticket::CLOSED], true)) {
            $ticket->status = Ticket::OPEN;
        }
        $ticket->save();

        return Redirect::refresh();
    }
}
