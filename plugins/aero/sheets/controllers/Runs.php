<?php namespace Aero\Sheets\Controllers;

use Aero\Sheets\Classes\ScopesToTenant;
use Aero\Sheets\Models\Run;
use Backend\Classes\Controller;
use BackendMenu;

class Runs extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.sheets.use', 'aero.sheets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sheets', 'sheets', 'runs');
    }

    public function view($id = null): void
    {
        $this->pageTitle = 'Ejecución #' . (int) $id;
        $this->vars['run'] = $this->scopeToTenant(Run::with('mapping'))->findOrFail((int) $id);
    }
}
