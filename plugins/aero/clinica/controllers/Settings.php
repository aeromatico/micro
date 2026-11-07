<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\ScopesToTenant;
use Aero\Clinica\Models\ClinicSettings;
use Backend\Classes\Controller;
use BackendMenu;

class Settings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.clinica.settings', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'settings');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** El tenant tiene una sola configuración: se abre directo (se crea al primer uso). */
    public function index()
    {
        if (!CurrentTenant::isAdmin()) {
            $id = CurrentTenant::id();
            if (!$id) {
                throw new \ApplicationException('No se pudo determinar su negocio.');
            }
            $row = ClinicSettings::firstOrCreate(['tenant_id' => $id]);

            return \Backend::redirect('aero/clinica/settings/update/' . $row->id);
        }

        $this->asExtension('ListController')->index();
    }
}
