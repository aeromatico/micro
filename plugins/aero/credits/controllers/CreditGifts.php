<?php namespace Aero\Credits\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * Regalos de suscripción comprados en /regalar (solo lectura): quién regaló,
 * a quién, si el pago llegó y si el cupón ya se entregó / canjeó.
 */
class CreditGifts extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'creditgifts');
    }
}
