<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\DealService;
use Aero\Realestate\Classes\LeadService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\ScopesToTenant;
use Aero\Realestate\Classes\VisitService;
use Backend\Classes\Controller;
use BackendMenu;

class Settings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'settings');
    }

    /** El tenant solo tiene una configuración: se abre directo (se crea al primer uso). */
    public function index()
    {
        if (!\Aero\Realestate\Classes\CurrentTenant::isAdmin()) {
            $id = \Aero\Realestate\Classes\CurrentTenant::id();
            if (!$id) {
                throw new \ApplicationException('No se pudo determinar su inmobiliaria.');
            }
            $row = \Aero\Realestate\Models\RealestateSettings::firstOrCreate(['tenant_id' => $id]);

            return \Backend::redirect('aero/realestate/settings/update/' . $row->id);
        }

        $this->asExtension('ListController')->index();
    }
}
