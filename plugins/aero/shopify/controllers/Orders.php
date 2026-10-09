<?php namespace Aero\Shopify\Controllers;

use Aero\Shopify\Classes\ScopesToTenant;
use Aero\Shopify\Jobs\MarkOrderPaidJob;
use Aero\Shopify\Models\Order;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/** Solo lectura: los pedidos los crea el webhook; aquí se reintenta la sincronización. */
class Orders extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.shopify.use', 'aero.shopify.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shopify', 'shopify', 'orders');
    }

    /** Reintenta marcar en Shopify un pedido cuyo QR ya se cobró. */
    public function index_onRetry()
    {
        $link = $this->scopeToTenant(Order::query())->findOrFail((int) post('id'));
        $qr = $link->qrCode();

        if (!$qr || $qr->status !== 'paid') {
            Flash::error('El QR de este pedido aún no figura como pagado.');

            return;
        }

        MarkOrderPaidJob::dispatch($link->id);
        Flash::success('Reintento encolado.');
    }
}
