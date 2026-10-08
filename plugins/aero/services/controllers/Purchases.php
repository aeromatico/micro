<?php namespace Aero\Services\Controllers;

use Aero\Services\Classes\Checkout;
use Aero\Services\Models\ServicePurchase;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/**
 * Compras de servicios de todos los tenants (superadmin): el cobro ya se
 * hizo al comprar (Aero.Credits); acá el equipo marca cada una como
 * entregada, o la cancela (con reembolso automático) si no se va a hacer.
 */
class Purchases extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.services.manage'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Services', 'services', 'purchases');
    }

    public function onFulfill()
    {
        $purchase = ServicePurchase::findOrFail((int) post('id'));

        try {
            Checkout::fulfill($purchase, BackendAuth::getUser()?->id);
        }
        catch (\InvalidArgumentException $e) {
            throw new ApplicationException($e->getMessage());
        }

        Flash::success('Compra marcada como entregada.');
    }

    public function onCancelPurchase()
    {
        $purchase = ServicePurchase::findOrFail((int) post('id'));

        try {
            Checkout::cancel($purchase, 'Cancelado por el equipo');
        }
        catch (\InvalidArgumentException $e) {
            throw new ApplicationException($e->getMessage());
        }

        Flash::success('Compra cancelada y reembolsada.');
    }
}
