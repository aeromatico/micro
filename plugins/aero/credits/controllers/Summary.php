<?php namespace Aero\Credits\Controllers;

use Aero\Credits\Classes\Credits;
use Aero\Credits\Models\CreditPurchase;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Resumen contable de la plataforma: cuántas monedas hay activas, cuántas se
 * vendieron / regalaron / consumieron, comisiones, ingresos en Bs y el
 * resultado de la auditoría de invariantes.
 */
class Summary extends Controller
{
    public $requiredPermissions = ['aero.credits.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Credits', 'credits', 'summary');
        $this->pageTitle = 'Resumen contable';
    }

    public function index()
    {
        $this->vars['summary'] = Credits::summary();
        $this->vars['money'] = Credits::moneySummary();
        $this->vars['problems'] = Credits::verify();
        $this->vars['revenue'] = (float) CreditPurchase::where('status', CreditPurchase::PAID)->sum('amount_bob');
        $this->vars['revenueMonth'] = (float) CreditPurchase::where('status', CreditPurchase::PAID)
            ->where('paid_at', '>=', now()->startOfMonth())->sum('amount_bob');
        $this->vars['pending'] = CreditPurchase::where('status', CreditPurchase::PENDING)->count();
        $this->vars['review'] = CreditPurchase::where('status', CreditPurchase::REVIEW)->get();
    }
}
