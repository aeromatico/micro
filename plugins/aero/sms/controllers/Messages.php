<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Message;
use Aero\Sms\Classes\CurrentTenant;
use Aero\Sms\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Messages extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sms.use', 'aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'messages');
    }

    public function preview_onCancel($recordId)
    {
        $message = $this->scopeToTenant(Message::query())->findOrFail($recordId);
        Sms::cancel($message) ? Flash::success('Mensaje cancelado y créditos devueltos.') : Flash::error('Solo se puede cancelar un mensaje en cola.');

        return \Backend::redirect('aero/sms/messages/preview/' . $recordId);
    }

    /** El tenant no necesita la columna de tenant: todo es suyo. */
    public function listExtendColumns($list): void
    {
        if (!CurrentTenant::isAdmin()) {
            $list->removeColumn('tenant_name');
            $list->removeColumn('consumer');
        }
    }
}
