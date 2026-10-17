<?php namespace Aero\Rgpd\Controllers;

use Aero\Rgpd\Classes\CurrentTenant;
use Aero\Rgpd\Classes\ScopesToTenant;
use Aero\Rgpd\Models\Config;
use Backend;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/**
 * El tenant tiene UNA configuración: index lo lleva directo a editarla. El
 * superadmin ve la lista (plataforma + tenants que ya la abrieron).
 */
class Configs extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.rgpd.use', 'aero.rgpd.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Rgpd', 'rgpd', 'configs');
    }

    public function index()
    {
        if (!CurrentTenant::isAdmin()) {
            $tenantId = CurrentTenant::id();
            if (!$tenantId) {
                Flash::error('No se pudo determinar tu sitio.');

                return Backend::redirect('backend');
            }

            return Backend::redirect('aero/rgpd/configs/update/' . Config::ensureForScope($tenantId)->id);
        }

        $this->asExtension('ListController')->index();
    }
}
