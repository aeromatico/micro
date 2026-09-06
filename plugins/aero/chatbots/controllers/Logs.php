<?php namespace Aero\Chatbots\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Logs extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.chatbots.manage', 'aero.chatbots.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Chatbots', 'chatbots', 'logs');
    }

    public function listExtendQuery($query): void
    {
        if ($tenantId = $this->getCurrentTenantId()) {
            $query->whereHas('bot', fn ($q) => $q->where('tenant_id', $tenantId));
        }
    }
}
