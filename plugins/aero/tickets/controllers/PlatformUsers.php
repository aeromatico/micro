<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\Audit;
use Aero\Tickets\Models\PlatformUser;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Usuarios del panel: consulta, actividad crítica, suspensión y rol global (personal de la plataforma). */
class PlatformUsers extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.tickets.admin.organizers', 'aero.tickets.admin.view', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'users');
    }

    protected function target(): PlatformUser
    {
        if (!\Aero\Tickets\Classes\Admin::can('organizers')) {
            throw new \ApplicationException('No tienes permiso para gestionar usuarios.');
        }
        $u = PlatformUser::findOrFail((int) post('id'));
        if ($u->is_superuser) {
            throw new \ApplicationException('Un superadministrador no se gestiona desde aquí.');
        }
        if ($u->id === $this->user->id) {
            throw new \ApplicationException('No puedes hacerlo sobre tu propia cuenta.');
        }

        return $u;
    }

    public function onSuspend()
    {
        $u = $this->target();
        $reason = trim((string) post('reason'));
        if (mb_strlen($reason) < 5) {
            throw new \ApplicationException('Indica el motivo de la suspensión.');
        }
        \BackendAuth::findThrottlerByUserId($u->id)->suspend();
        Audit::record('user.suspended', 'user', $u->id, ['note' => $u->login . ': ' . $reason]);
        Flash::warning('Cuenta suspendida: no podrá iniciar sesión.');

        return \Redirect::refresh();
    }

    public function onEnable()
    {
        $u = $this->target();
        \BackendAuth::findThrottlerByUserId($u->id)->unsuspend();
        Audit::record('user.enabled', 'user', $u->id, ['note' => $u->login . ' ' . trim((string) post('note'))]);
        Flash::success('Cuenta habilitada.');

        return \Redirect::refresh();
    }

    /** Rol global de la plataforma (solo roles «Entradas ·» y solo para personal sin espacio de trabajo propio). */
    public function onSetRole()
    {
        if (!$this->user->hasAccess('aero.tickets.superadmin')) {
            throw new \ApplicationException('Solo un superadministrador asigna roles globales.');
        }
        $u = $this->target();
        if ($u->tenantsOwned()->isNotEmpty()) {
            throw new \ApplicationException('Este usuario administra un espacio de trabajo: cambiar su rol le quitaría el acceso a su organización.');
        }
        $roleId = (int) post('role_id');
        $role = $roleId ? \Backend\Models\UserRole::where('code', 'like', 'tickets_%')->findOrFail($roleId) : null;
        if (!$role && !($u->role && str_starts_with((string) $u->role->code, 'tickets_'))) {
            throw new \ApplicationException('Solo se quita un rol global de Entradas.');
        }
        $before = $u->role?->name;
        $u->role_id = $role?->id;
        $u->save();
        Audit::record('user.role_changed', 'user', $u->id, ['changes' => ['role' => [$before, $role?->name]], 'note' => $u->login]);
        Flash::success($role ? 'Rol asignado: ' . $role->name : 'Rol global retirado.');

        return \Redirect::refresh();
    }
}
