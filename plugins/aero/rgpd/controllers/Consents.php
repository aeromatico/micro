<?php namespace Aero\Rgpd\Controllers;

use Aero\Rgpd\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

/** Registro de consentimientos: solo lectura. */
class Consents extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.rgpd.use', 'aero.rgpd.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Rgpd', 'rgpd', 'consents');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeToTenant($query);
        $query->orderByDesc('id');
    }
}
