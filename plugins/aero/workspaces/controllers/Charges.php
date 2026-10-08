<?php namespace Aero\Workspaces\Controllers;

use Aero\Workspaces\Classes\Billing;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Cobros de Workspaces para el superadmin: cuántos puntos entraron por
 * contrataciones, mensajes y encargos, por agente y por tenant.
 */
class Charges extends Controller
{
    public $requiredPermissions = ['aero.workspaces.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Workspaces', 'workspaces', 'charges');
        $this->pageTitle = 'Cobros de Workspaces';
    }

    public function index(): void
    {
        $days = in_array((int) input('days'), [7, 30, 90, 365], true) ? (int) input('days') : 30;
        $this->vars['report'] = Billing::report($days);
        $this->vars['tenantNames'] = $this->tenantNames($this->vars['report']['tenants']);
    }

    /** @return array<int,string> */
    protected function tenantNames(array $rows): array
    {
        $ids = array_column($rows, 'tenant_id');

        if (!$ids || !class_exists(\Aero\Sites\Models\Tenant::class)) {
            return [];
        }

        return \Aero\Sites\Models\Tenant::whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
