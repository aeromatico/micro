<?php namespace Aero\School\Controllers;

use Aero\School\Classes\CurrentTenant;
use Aero\School\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Guardians extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'guardians');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el colegio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** Crea (o vincula) el usuario del portal del padre. */
    public function update_onCreateUser($recordId = null)
    {
        $guardian = $this->formFindModelObject($recordId);

        try {
            (new \Aero\School\Classes\GuardianUsers())->ensureUser($guardian);
        } catch (\Aero\School\Classes\SchoolException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success('Usuario del portal creado y vinculado. El apoderado puede entrar con «Olvidé mi contraseña».');

        return \Redirect::refresh();
    }
}
