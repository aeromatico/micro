<?php namespace Aero\Notify\Controllers;

use Aero\Notify\Models\Rule;
use Aero\Notify\Traits\ScopesToTenant;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/**
 * Reglas de entrega: evento x audiencia x canal x plantilla.
 *
 * tenant_id = 0 es una regla de plataforma (la heredan todos los tenants sin
 * reglas propias para ese evento, ver Rule::effectiveFor). Administrar reglas
 * globales requiere manage_global_rules; las propias de un tenant,
 * manage_rules — son dos permisos porque hoy solo se conceden a superadmin
 * (ver updates/revoke_tenant_admin_permissions.php), pero el controller ya
 * queda listo para el día que un tenant_admin reciba manage_rules sin
 * manage_global_rules.
 */
class Rules extends Controller
{
    use ScopesToTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.notify.manage_rules', 'aero.notify.manage_global_rules'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Aero.Notify', 'notify', 'notify-rules');
    }

    /**
     * Un tenant_admin que crea una regla nueva no puede elegir "Plataforma":
     * su alcance queda fijo al tenant que está operando.
     */
    public function formExtendModel($model)
    {
        if (!$model->exists && !$this->isSuperadmin()) {
            $model->tenant_id = $this->effectiveTenantId();
        }

        return $model;
    }

    public function formBeforeSave($model): void
    {
        $this->assertCanManage($model);
    }

    public function formBeforeDelete($model): void
    {
        $this->assertCanManage($model);
    }

    protected function assertCanManage(Rule $model): void
    {
        $user = BackendAuth::getUser();
        $isGlobal = (int) $model->tenant_id === Rule::GLOBAL_TENANT;
        $permission = $isGlobal ? 'aero.notify.manage_global_rules' : 'aero.notify.manage_rules';

        if (!$user || !$user->hasAccess([$permission])) {
            throw new ApplicationException('No tenés permiso para modificar esta regla.');
        }

        if (!$isGlobal && !$this->isSuperadmin() && (int) $model->tenant_id !== $this->effectiveTenantId()) {
            throw new ApplicationException('No podés modificar reglas de otro tenant.');
        }
    }
}
