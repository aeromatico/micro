<?php namespace Aero\Tickets\Controllers;

use Aero\Tickets\Classes\AdminStats;
use Backend\Classes\Controller;
use BackendMenu;

/** Panel global de la plataforma: indicadores, actividad y pendientes. Solo lectura. */
class AdminDashboard extends Controller
{
    public $requiredPermissions = ['aero.tickets.admin.view', 'aero.tickets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Tickets', 'tickets_admin', 'dashboard');
        $this->pageTitle = 'Panel global';
    }

    public function index(): void
    {
        $this->vars['s'] = AdminStats::overview();
        $this->vars['tenants'] = class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::pluck('name', 'id')->all() : [];
    }
}
