<?php namespace Aero\School\Controllers;

use Aero\School\Classes\CurrentTenant;
use Aero\School\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Announcements extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.school.use', 'aero.school.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.School', 'school', 'announcements');
    }

    /** Lo que crea un tenant es suyo; el superadmin elige el colegio en el formulario. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    public function update_onSend($recordId = null)
    {
        $a = $this->formFindModelObject($recordId);
        if ($a->status === 'sending') {
            throw new \ApplicationException('Este comunicado ya se está enviando.');
        }

        $count = app(\Aero\School\Classes\Announcer::class)->recipients($a)->count();
        if (!$count) {
            throw new \ApplicationException('Ningún apoderado de esta audiencia tiene celular registrado.');
        }

        $a->update(['status' => 'sending', 'created_by' => $a->created_by ?: \BackendAuth::getUser()?->id]);
        \Aero\School\Jobs\SendAnnouncementJob::dispatch($a->id);
        \Flash::success("Enviando a {$count} apoderados. El avance se ve en esta pantalla.");

        return \Redirect::refresh();
    }
}
