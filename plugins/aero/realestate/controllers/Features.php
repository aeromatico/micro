<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\CurrentTenant;
use Aero\Realestate\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Features extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'features');
    }

    /** La lista muestra el catálogo global + lo propio; editar sigue limitado a lo propio (formExtendQuery). */
    public function listExtendQuery($query): void
    {
        if (!CurrentTenant::isAdmin()) {
            $id = CurrentTenant::id();
            $query->where(fn ($q) => $q->whereNull('tenant_id')->when($id, fn ($w) => $w->orWhere('tenant_id', $id)));
        }
    }
}
