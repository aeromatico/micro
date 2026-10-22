<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\AnticreticoService;
use Aero\Realestate\Classes\DealService;
use Aero\Realestate\Classes\LeadService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\ScopesToTenant;
use Aero\Realestate\Classes\VisitService;
use Backend\Classes\Controller;
use BackendMenu;

class Pipeline extends Controller
{
    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'pipeline');
    }

    public function index(): void
    {
        $this->pageTitle = 'Embudo comercial';
        $stages = \Aero\Realestate\Models\Lead::$stages;
        $board = [];
        foreach ($stages as $k => $label) {
            $q = \Aero\Realestate\Models\Lead::visible()->where('stage', $k)->with(['client', 'property', 'agent'])->orderByRaw('next_follow_up_at IS NULL, next_follow_up_at')->orderByDesc('id');
            if (in_array($k, ['closed_won', 'closed_lost'], true)) {
                $q->where('closed_at', '>=', now()->subDays(30));
            }
            $board[$k] = ['label' => $label, 'total' => (clone $q)->count(), 'items' => $q->limit(25)->get()];
        }
        $this->vars['board'] = $board;
        $this->vars['visits'] = \Aero\Realestate\Models\Visit::visible()->upcoming()->with(['property', 'client', 'agent'])->orderBy('starts_at')->limit(15)->get();
    }
}
