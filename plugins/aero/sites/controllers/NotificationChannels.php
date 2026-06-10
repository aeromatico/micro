<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use BackendMenu;
use Backend\Classes\Controller;

class NotificationChannels extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $formConfig    = 'config_form.yaml';
    public $listConfig    = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['aero.sites.manage_contact'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'notificationchannels');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
        }
    }

    public function getTypeOptions(): array
    {
        return [
            'email'    => 'Email',
            'whatsapp' => 'WhatsApp (Meta Cloud API)',
            'telegram' => 'Telegram Bot',
            'sms'      => 'SMS (Twilio)',
        ];
    }
}
