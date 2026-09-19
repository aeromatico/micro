<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Message;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Messages extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'messages');
    }

    public function preview_onCancel($recordId)
    {
        $message = Message::findOrFail($recordId);
        Sms::cancel($message) ? Flash::success('Mensaje cancelado y créditos devueltos.') : Flash::error('Solo se puede cancelar un mensaje en cola.');

        return \Backend::redirect('aero/sms/messages/preview/' . $recordId);
    }
}
