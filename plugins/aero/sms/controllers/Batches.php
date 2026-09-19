<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Batch;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Batches extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'batches');
    }

    public function preview_onCancel($recordId)
    {
        $n = Sms::cancelBatch(Batch::findOrFail($recordId));
        Flash::success("Lote cancelado: {$n} mensajes anulados y sus créditos devueltos.");

        return \Backend::redirect('aero/sms/batches/preview/' . $recordId);
    }
}
