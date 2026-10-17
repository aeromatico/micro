<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\ClinicSettings;
use Backend\Classes\Controller;
use BackendMenu;

class Settings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'settings');
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

            return \Backend::redirect('aero/medical/settings/update/' . $row->id);
        }

        $this->asExtension('ListController')->index();
    }
}
