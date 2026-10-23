<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\GivingStats;
use Aero\Church\Models\Branch;
use Backend\Classes\Controller;
use BackendMenu;

/** Resumen de aportes: solo cifras agregadas (sin nombres), apto para el pastor. */
class Giving extends Controller
{
    public $requiredPermissions = ['aero.church.use', 'aero.church.treasury', 'aero.church.pastor', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'giving');
        $this->pageTitle = 'Resumen de aportes';
    }

    public function index()
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get('from')) ? get('from') : now()->startOfYear()->toDateString();
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get('to')) ? get('to') : now()->toDateString();
        $branchId = Branch::visible()->whereKey((int) get('branch'))->value('id');

        $this->vars['from'] = $from;
        $this->vars['to'] = $to;
        $this->vars['branchId'] = $branchId;
        $this->vars['branches'] = Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
        $this->vars['stats'] = new GivingStats($from, $to, $branchId ? (int) $branchId : null);
    }
}
