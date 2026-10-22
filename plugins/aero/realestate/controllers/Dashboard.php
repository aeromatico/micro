<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\CurrentTenant;
use Aero\Realestate\Classes\Stats;
use Backend\Classes\Controller;
use BackendMenu;

/** Resumen de la inmobiliaria: catálogo, comercial, anticréticos y vencimientos del periodo elegido. */
class Dashboard extends Controller
{
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'dashboard');
        $this->pageTitle = 'Resumen';
    }

    public function index(): void
    {
        $stats = Stats::forPanel((array) request()->query());
        $this->vars['stats'] = $stats;
        $this->vars['d'] = $stats->dashboard();
        $this->vars['isAdmin'] = CurrentTenant::isAdmin();
        $this->vars['canMoney'] = CurrentTenant::isAdmin() || (bool) \BackendAuth::getUser()?->hasAccess('aero.realestate.use');
        $this->vars['tenants'] = CurrentTenant::isAdmin() && class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all() : [];
    }
}
