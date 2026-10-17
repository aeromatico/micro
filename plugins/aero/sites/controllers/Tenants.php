<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\TenantProvisioner;
use Aero\Sites\Models\Tenant;
use Backend;
use BackendAuth;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Redirect;

class Tenants extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $formConfig     = 'config_form.yaml';
    public $listConfig     = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    protected ?array $lastCredentials = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'tenants');
    }

    // -------------------------------------------------------------------------
    // Delete — bulk (list checkboxes) and single (edit page)
    // -------------------------------------------------------------------------

    public function onDelete(): mixed
    {
        $checkedIds = post('checked');

        if (!is_array($checkedIds) || empty($checkedIds)) {
            Flash::error('No se seleccionaron tenants.');
            return $this->listRefresh();
        }

        $count = 0;
        foreach ($checkedIds as $id) {
            $tenant = Tenant::find((int) $id);
            if ($tenant) {
                $tenant->purge();
                $count++;
            }
        }

        Flash::success("{$count} tenant(s) eliminado(s) permanentemente.");
        return $this->listRefresh();
    }

    public function onDeleteTenant(mixed $recordId = null): mixed
    {
        $tenant = Tenant::findOrFail((int) $recordId);
        $name   = $tenant->name;
        $tenant->purge();

        Flash::success("Tenant «{$name}» eliminado permanentemente.");
        return Redirect::to(Backend::url('aero/sites/tenants'));
    }

    // -------------------------------------------------------------------------
    // Ingresar como administrador del tenant (solo superadmin)
    // -------------------------------------------------------------------------

    public function onImpersonate(mixed $recordId = null): mixed
    {
        $tenant = Tenant::findOrFail((int) ($recordId ?: post('recordId')));
        $admin  = $tenant->backendUser;

        if (!$admin) {
            Flash::error('Este tenant no tiene un administrador asignado.');
            return null;
        }

        if ($admin->is_superuser) {
            Flash::error('El administrador del tenant es superusuario; no se puede suplantar.');
            return null;
        }

        BackendAuth::impersonate($admin);
        trace_log("Impersonación: {$this->user->login} → {$admin->login} (tenant {$tenant->id})");

        // El admin puede tener varios espacios: abrir justo este.
        session(['aero_sites_active_tenant' => $tenant->id]);

        return Redirect::to($tenant->backendUrl());
    }

    // -------------------------------------------------------------------------
    // Provisioning
    // -------------------------------------------------------------------------

    public function onCreate(): void
    {
        // Delegate to FormController, but we intercept after save
    }

    public function formAfterCreate(Tenant $tenant): void
    {
        $this->lastCredentials = app(TenantProvisioner::class)->provisionTenant($tenant);
    }

    // -------------------------------------------------------------------------
    // Show credentials after create
    // -------------------------------------------------------------------------

    public function create_onSave(): mixed
    {
        $result = $this->asExtension('FormController')->create_onSave();

        if ($this->lastCredentials) {
            $this->vars['credentials'] = $this->lastCredentials;
            Flash::success('Tenant creado correctamente. Guarda las credenciales mostradas.');
            return $this->makePartial('credentials_flash', $this->vars);
        }

        return $result;
    }

}
