<?php namespace Aero\School\Controllers;

use Aero\School\Classes\CurrentTenant;
use Aero\School\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Courses extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'courses');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el colegio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** Carga las áreas del currículo base (por nivel) en el curso, sin duplicar. */
    public function update_onSeedSubjects($recordId = null)
    {
        $course = $this->formFindModelObject($recordId);
        $n = \Aero\School\Classes\Curriculum::seedCourse($course);
        \Flash::success($n ? "Se cargaron {$n} áreas del currículo base." : 'El curso ya tiene todas las áreas base.');

        return \Redirect::refresh();
    }
}
