<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Routines extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'routines');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el gimnasio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    public function printout($id)
    {
        $this->pageTitle = 'Rutina';
        $this->vars['routine'] = $this->scopeToTenant(\Aero\Gym\Models\Routine::with(['items', 'member', 'instructor']))->findOrFail($id);
    }

    public function update_onAssign($recordId = null)
    {
        $routine = $this->formFindModelObject($recordId);
        $member = \Aero\Gym\Models\Member::visible()->findOrFail((int) post('member_id'));
        $copy = $routine->assignTo($member);
        \Flash::success('Rutina copiada a ' . $member->name . '.');

        return \Redirect::to(\Backend::url('aero/gym/routines/update/' . $copy->id));
    }
}
