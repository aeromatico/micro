<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\CurrentTenant;
use Aero\Church\Classes\ScopesToTenant;
use Aero\Church\Models\ChurchSettings;
use Backend\Classes\Controller;
use BackendMenu;

class GivingSettings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.treasury', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'givingsettings');
    }

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
                throw new \ApplicationException('No se pudo determinar su iglesia.');
            }

            return \Backend::redirect('aero/church/givingsettings/update/' . ChurchSettings::forTenant($id)->id);
        }

        return $this->asExtension('ListController')->index();
    }
}
