<?php namespace Aero\Pos\Controllers;

use Aero\Pos\Classes\PosProvisioner;
use Aero\Pos\Classes\ShiftReport;
use Aero\Pos\Classes\ShiftService;
use Aero\Pos\Models\Shift;
use Aero\Pos\Models\Terminal;
use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/** Turnos de caja: listado, apertura, movimientos de efectivo, cierre con arqueo y reporte. */
class Shifts extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.pos.use', 'aero.pos.manage_shifts'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Pos', 'pos', 'pos-turnos');
    }

    public function index()
    {
        if ($tenantId = $this->getCurrentTenantId()) {
            PosProvisioner::ensureDefaults($tenantId);
        }
        $this->asExtension('ListController')->index();
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function open()
    {
        $this->pageTitle = 'Abrir turno';
        $tenantId = $this->getCurrentTenantId();
        $this->vars['terminals'] = Terminal::forTenant($tenantId)->where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($t) => !$t->openShift());
    }

    public function onOpen()
    {
        try {
            $shift = (new ShiftService())->open(
                (int) $this->getCurrentTenantId(), (int) post('terminal_id'), BackendAuth::getUser()->id, (float) post('opening_cash', 0)
            );
        } catch (OrderException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        Flash::success('Turno abierto.');

        return \Redirect::to(\Backend::url('aero/pos/shifts/view/' . $shift->id));
    }

    public function view($id = null)
    {
        $this->pageTitle = 'Turno de caja';
        $shift = $this->findShift((int) $id);
        $this->vars['shift'] = $shift;
        $this->vars['report'] = $shift->isOpen() ? ShiftReport::build($shift) : ($shift->summary ?: ShiftReport::build($shift));
        $this->vars['movements'] = $shift->movements()->with('user')->orderBy('id')->get();
        $this->vars['canForce'] = BackendAuth::getUser()->hasAccess('aero.pos.manage_shifts');
    }

    public function onAddMovement()
    {
        $shift = $this->findShift((int) post('shift_id'));
        $this->assertCanOperate($shift);

        try {
            (new ShiftService())->addMovement($shift, (string) post('type'), (float) post('amount'), (string) post('reason'), BackendAuth::getUser()->id);
        } catch (OrderException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        Flash::success('Movimiento registrado.');

        return \Redirect::refresh();
    }

    public function onCloseShift()
    {
        $shift = $this->findShift((int) post('shift_id'));
        $this->assertCanOperate($shift);

        if (post('counted_cash') === null || post('counted_cash') === '') {
            throw new \ApplicationException('Escribe el efectivo que contaste en caja.');
        }

        try {
            (new ShiftService())->close($shift, (float) post('counted_cash'), BackendAuth::getUser()->id, trim((string) post('notes')) ?: null);
        } catch (OrderException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        Flash::success('Turno cerrado.');

        return \Redirect::refresh();
    }

    protected function findShift(int $id): Shift
    {
        return Shift::forTenant((int) $this->getCurrentTenantId())->with('terminal', 'opener', 'closer')->findOrFail($id);
    }

    /** Quien abrió el turno puede operarlo; cerrar el de otra persona exige «gestionar turnos». */
    protected function assertCanOperate(Shift $shift): void
    {
        $user = BackendAuth::getUser();
        if ((int) $shift->opened_by_user_id !== (int) $user->id && !$user->hasAccess('aero.pos.manage_shifts')) {
            throw new \ApplicationException('Este turno lo abrió otra persona. Pide a un supervisor que lo gestione.');
        }
    }
}
