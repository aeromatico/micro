<?php namespace Aero\Crm\Controllers;

use Aero\Sites\Models\TenantInvite;
use Aero\Sites\Models\TenantUser;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;
use Validator;

/**
 * Equipo interno del tenant: a quién se le dio acceso para gestionarlo como
 * si fuera el propietario original. No es el Team/TeamMember de asignación de
 * Deals (ver models/Team.php) — eso sigue existiendo para otro caso de uso,
 * este controlador gestiona Aero.Sites\TenantUser, que es lo que
 * ResolvesCurrentTenant ya usa para resolver "quién puede administrar este
 * tenant" en todo el proyecto.
 *
 * Se invita solo por correo (+ WhatsApp opcional): si el correo ya tiene
 * cuenta de backend (p.ej. es dueño de otro tenant) se lo agrega directo como
 * colaborador; si no, se le crea la cuenta y se le manda el enlace para
 * activarla. Ver Aero\Sites\Models\TenantInvite::send().
 */
class Team extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.crm.manage_teams'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-equipo');
    }

    public function listExtendQuery($query): void
    {
        $query->where('tenant_id', $this->requireTenant()->id);
    }

    public function onLoadInviteForm()
    {
        return $this->makePartial('invite_form');
    }

    public function onInvite()
    {
        $tenant = $this->requireTenant();

        $data = post();

        $validator = Validator::make($data, [
            'email' => 'required|email',
            'phone' => 'nullable|string|max:30',
            'role'  => 'required|in:admin,moderator,user',
        ]);

        if ($validator->fails()) {
            throw new \ValidationException($validator);
        }

        $invite = TenantInvite::send(
            $tenant,
            $data['email'],
            $data['phone'] ?? null,
            $data['role'],
            BackendAuth::getUser()
        );

        if ($invite->status === 'accepted') {
            Flash::success("{$invite->email} ya tenía cuenta: se le dio acceso a este tenant.");
        } else {
            Flash::success("Se envió la invitación a {$invite->email}.");
        }

        return $this->listRefresh();
    }

    public function onDelete()
    {
        $tenant  = $this->requireTenant();
        $checked = (array) post('checked');

        if (!$checked) {
            Flash::error('No seleccionaste ningún miembro.');
            return $this->listRefresh();
        }

        $removed  = 0;
        $protected = 0;

        foreach (TenantUser::where('tenant_id', $tenant->id)->whereIn('id', $checked)->get() as $tenantUser) {
            if ((int) $tenantUser->user_id === (int) $tenant->backend_user_id) {
                $protected++;
                continue;
            }

            $tenantUser->delete();
            $removed++;
        }

        if ($removed) {
            Flash::success("{$removed} miembro(s) quitado(s) del equipo.");
        }

        if ($protected) {
            Flash::warning("{$protected} registro(s) se omitieron: es el propietario original del tenant.");
        }

        return $this->listRefresh();
    }

    protected function requireTenant(): \Aero\Sites\Models\Tenant
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            throw new \ApplicationException('No se pudo determinar el tenant actual.');
        }

        return $tenant;
    }
}
