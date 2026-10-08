<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Settings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'settings');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el gimnasio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** El tenant solo tiene una configuración: se abre directo (se crea al primer uso). */
    public function index()
    {
        if (!CurrentTenant::isAdmin()) {
            $id = CurrentTenant::id();
            if (!$id) {
                throw new \ApplicationException('No se pudo determinar su gimnasio.');
            }
            $row = \Aero\Gym\Models\GymSettings::firstOrCreate(['tenant_id' => $id]);

            return \Backend::redirect('aero/gym/settings/update/' . $row->id);
        }

        $this->asExtension('ListController')->index();
    }
}
