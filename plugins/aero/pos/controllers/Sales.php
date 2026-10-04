<?php namespace Aero\Pos\Controllers;

use Aero\Pos\Models\Sale;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendMenu;

/** Ventas hechas desde el POS (solo lectura; los cambios se hacen desde la app o en Pedidos). */
class Sales extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.pos.reports'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Pos', 'pos', 'pos-ventas');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function view($id = null)
    {
        $this->pageTitle = 'Venta';
        $sale = Sale::forTenant((int) $this->getCurrentTenantId())
            ->with(['order.items', 'order.customer', 'order.currency', 'payments.method', 'cashier', 'pos_table', 'terminal', 'shift'])
            ->findOrFail((int) $id);
        $this->vars['sale'] = $sale;
    }
}
