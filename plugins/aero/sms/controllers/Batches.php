<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Batch;
use Aero\Sms\Classes\CurrentTenant;
use Aero\Sms\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Batches extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sms.use', 'aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'batches');
    }

    public function preview_onCancel($recordId)
    {
        $n = Sms::cancelBatch($this->scopeToTenant(Batch::query())->findOrFail($recordId));
        Flash::success("Lote cancelado: {$n} mensajes anulados y sus créditos devueltos.");

        return \Backend::redirect('aero/sms/batches/preview/' . $recordId);
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
