<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Models\Contract;
use Backend\Classes\Controller;
use BackendMenu;

/** Seguimiento de anticréticos: vigentes, próximos a vencer y vencidos, con el capital en juego por moneda. */
class Tracking extends Controller
{
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'tracking');
        $this->pageTitle = 'Seguimiento de anticréticos';
    }

    public function index(): void
    {
        $base = fn () => Contract::visible();
        $with = ['property', 'client', 'owner', 'agent'];

        $active = $base()->active();
        $this->vars['capitalByCurrency'] = $base()->active()->selectRaw('currency, SUM(delivered_amount - returned_amount) as pending, COUNT(*) as n')
            ->groupBy('currency')->get();
        $this->vars['counts'] = [
            'active'   => (clone $active)->count(),
            'expiring' => $base()->expiring(Contract::EXPIRING_DAYS, false)->count(),
            'overdue'  => $base()->active()->whereDate('end_date', '<', now()->toDateString())->count(),
            'dispute'  => $base()->where('in_dispute', true)->count(),
            'draft'    => $base()->whereIn('status', ['draft', 'reserved'])->count(),
        ];
        $this->vars['expiring'] = $base()->expiring(Contract::EXPIRING_DAYS, true)->with($with)->orderBy('end_date')->limit(50)->get();
        $this->vars['disputes'] = $base()->where('in_dispute', true)->with($with)->limit(20)->get();
        $this->vars['returns'] = $base()->active()->where('return_status', 'partial')->with($with)->limit(20)->get();
    }
}
