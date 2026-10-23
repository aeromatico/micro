<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\CurrentTenant;
use Aero\Church\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Funds extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.treasury', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'funds');
    }

    /** Una iglesia nueva arranca con diezmo, ofrenda y donación. */
    protected function ensureDefaultFunds(): void
    {
        $id = CurrentTenant::isAdmin() ? null : CurrentTenant::id();
        if ($id && !\Aero\Church\Models\Fund::where('tenant_id', $id)->exists()) {
            \Aero\Church\Models\Fund::seedDefaults($id);
        }
    }

    public function index()
    {
        $this->ensureDefaultFunds();

        return $this->asExtension('ListController')->index();
    }
}
