<?php namespace Aero\Office\Controllers;

use Aero\Office\Models\Booking;
use Aero\Office\Models\Branch;
use Aero\Office\Models\Worker;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Carbon\Carbon;

/** Agenda diaria (columnas por profesional) y semanal (columnas por día). */
class Agenda extends Controller
{
    public $requiredPermissions = ['aero.office.use', 'aero.office.reception', 'aero.office.professional', 'aero.office.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Office', 'office', 'agenda');
        $this->pageTitle = 'Agenda';
    }

    public function index(): void
    {
        try {
            $date = Carbon::parse((string) input('date', today()->toDateString()))->startOfDay();
        } catch (\Throwable $e) {
            $date = today();
        }
        $view = input('view') === 'week' ? 'week' : 'day';
        $branchId = (int) input('branch');
        $workerId = (int) input('worker');

        $from = $view === 'week' ? $date->copy()->startOfWeek() : $date->copy();
        $to = $view === 'week' ? $date->copy()->endOfWeek() : $date->copy()->endOfDay();

        $q = Booking::visibleToUser()->whereBetween('starts_at', [$from, $to])
            ->whereIn('status', ['pending', 'confirmed', 'in_service', 'completed', 'no_show'])
            ->with(['customer', 'worker', 'branch'])->orderBy('starts_at');
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        if ($workerId) {
            $q->where('worker_id', $workerId);
        }

        $this->vars += [
            'date'     => $date,
            'view'     => $view,
            'from'     => $from,
            'bookings' => $q->get(),
            'branches' => Branch::visible()->orderBy('name')->pluck('name', 'id')->all(),
            'workers'  => $this->visibleWorkers(),
            'branchId' => $branchId,
            'workerId' => $workerId,
        ];
    }

    protected function visibleWorkers(): array
    {
        $q = Worker::visible()->orderBy('name');
        $u = BackendAuth::getUser();
        if (!$u->is_superuser && !$u->hasAccess('aero.office.use') && !$u->hasAccess('aero.office.reception') && !$u->hasAccess('aero.office.superadmin')) {
            $q->where('user_id', $u->id);
        }

        return $q->pluck('name', 'id')->all();
    }
}
