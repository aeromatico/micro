<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Classes\TenantUsers;
use Aero\Crm\Models\Ticket;
use Aero\Crm\Models\TicketReply;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

class Tickets extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_tickets'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-tickets');
    }

    public function listExtendQuery($query): void
    {
        $query->visibleTo(BackendAuth::getUser(), TenantUsers::currentTenantId())
            ->with(['department', 'assignee'])
            ->withCount('replies');
    }

    /** Sin esto se podría abrir por URL un ticket de otro departamento/tenant. */
    public function formExtendQuery($query): void
    {
        $query->visibleTo(BackendAuth::getUser(), TenantUsers::currentTenantId());
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = TenantUsers::currentTenantId();
        }
    }

    public function formAfterSave($model): void
    {
        $data = post('Ticket', []);
        $body = trim((string) ($data['new_reply'] ?? ''));

        if ($body === '') {
            return;
        }

        $internal = !empty($data['reply_internal']);

        TicketReply::create([
            'ticket_id'   => $model->id,
            'user_id'     => BackendAuth::getUser()?->id,
            'body'        => $body,
            'is_internal' => $internal,
        ]);

        if (!$internal && !$model->first_response_at) {
            $model->first_response_at = now();
            $model->forceSave();
        }
    }
}
