<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class ExamCatalogs extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'examcatalogs');
    }

    public function index()
    {
        if ($id = CurrentTenant::id()) {
            \Aero\Medical\Models\ExamCatalog::ensureDefaults($id);
        }

        $this->asExtension('ListController')->index();
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
