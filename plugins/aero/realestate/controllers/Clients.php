<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\CurrentTenant;
use Aero\Realestate\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Clients extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'clients');
    }

    /** Crea (o vincula) el usuario RainLab.User de la persona para que entre al portal con su correo. */
    public function update_onCreateUser($recordId = null)
    {
        $person = \Aero\Realestate\Models\Client::visible()->findOrFail((int) $recordId);
        try {
            app(\Aero\Realestate\Classes\PersonUsers::class)->ensureUser($person);
        } catch (\Aero\Realestate\Classes\RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Usuario vinculado. Puede entrar al portal con su correo.');

        return \Redirect::refresh();
    }
}
