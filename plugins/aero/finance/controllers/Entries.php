<?php namespace Aero\Finance\Controllers;

use Aero\Finance\Classes\ScopesToTenant;
use Aero\Finance\Models\JournalEntry;
use Backend\Classes\Controller;
use BackendMenu;

/** Libro diario: solo lectura. Los asientos se crean con movimientos y se corrigen anulando. */
class Entries extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.finance.use', 'aero.finance.superadmin'];

    public $pageTitle = 'Libro diario';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Finance', 'finance', 'entries');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeToTenant($query);
        $query->orderByDesc('date')->orderByDesc('number');
    }

    public function view($id = null): void
    {
        $this->pageTitle = 'Asiento';
        $this->vars['entry'] = JournalEntry::visible()->with('lines.account')->findOrFail((int) $id);
    }
}
