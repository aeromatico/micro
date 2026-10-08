<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\Reports as ReportService;
use Backend\Classes\Controller;
use BackendMenu;
use Carbon\Carbon;

class Reports extends Controller
{
    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public $pageTitle = 'Reportes';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'reports');
    }

    public function index(): void
    {
        try {
            $to = Carbon::parse(input('to', today()->toDateString()))->endOfDay();
            $from = Carbon::parse(input('from', today()->subDays(29)->toDateString()))->startOfDay();
        } catch (\Throwable $e) {
            $to = today()->endOfDay();
            $from = today()->subDays(29)->startOfDay();
        }
        if ($from->gt($to) || $from->diffInDays($to) > 400) {
            $from = $to->copy()->subDays(29)->startOfDay();
        }

        // Falla cerrado: sin tenant y sin ser superadmin, no hay reporte.
        $tenantId = CurrentTenant::isAdmin() ? null : CurrentTenant::id();
        if (!CurrentTenant::isAdmin() && !$tenantId) {
            throw new \ApplicationException('No se pudo determinar su gimnasio.');
        }

        $r = new ReportService($tenantId);
        $this->vars += [
            'from'      => $from,
            'to'        => $to,
            'summary'   => $r->summary(),
            'days'      => $r->attendanceByDay($from, $to),
            'classes'   => $r->popularClasses($from, $to),
            'occupancy' => $r->occupancy($from, $to),
            'churn'     => $r->renewalsVsChurn($from, $to),
        ];
    }
}
