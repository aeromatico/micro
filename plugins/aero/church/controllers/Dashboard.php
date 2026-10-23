<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\CurrentTenant;
use Aero\Church\Classes\ReportService;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** Panel de indicadores de la iglesia. Los bloques sensibles dependen del permiso de quien mira. */
class Dashboard extends Controller
{
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'dashboard');
        $this->pageTitle = 'Resumen';
    }

    public function index()
    {
        if (!CurrentTenant::isAdmin() && !CurrentTenant::id()) {
            throw new \ApplicationException('No se pudo determinar su iglesia.');
        }

        $user = BackendAuth::getUser();
        $pastoral = $user->hasAnyAccess(['aero.church.use', 'aero.church.pastor', 'aero.church.superadmin']);
        $svc = new ReportService(CurrentTenant::isAdmin() ? null : CurrentTenant::id());

        // Los aportes (aun agregados) y las peticiones de oración solo para la dirección; secretaría ve el resto.
        $this->vars['d'] = $svc->dashboard($pastoral, $pastoral);
        $this->vars['pastoral'] = $pastoral;
    }
}
