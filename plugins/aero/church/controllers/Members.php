<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Members extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'members');
    }

    /** Crea (o vincula) el usuario del portal del miembro y le envía la invitación para elegir contraseña. */
    public function update_onCreatePortalUser($recordId = null)
    {
        $member = \Aero\Church\Models\Member::visible()->findOrFail((int) $recordId);
        $domain = class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($member->tenant_id)?->primary_domain : null;

        try {
            $users = app(\Aero\Church\Classes\ChurchUsers::class);
            $users->ensureUser($member, $domain ? 'https://' . $domain . '/cuenta/restablecer' : null);
        } catch (\Aero\Church\Classes\ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success($users->created ? 'Acceso creado: le enviamos un correo para elegir su contraseña.' : 'Acceso vinculado a su cuenta existente.');

        return \Redirect::refresh();
    }
}
