<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\ProFeatures;
use Backend\Classes\Controller;

/**
 * Invitación estática a mejorar al plan PRO. Cualquier usuario del panel puede
 * abrirla; ProFeatures::blocks() redirige aquí desde las pantallas PRO.
 */
class Upgrade extends Controller
{
    public $requiredPermissions = [];

    public function index()
    {
        $this->pageTitle = 'Mejorá a PRO';
        $catalog = ProFeatures::catalog();
        $from = strtolower((string) get('from'));
        $this->vars['feature'] = $catalog[$from]['label'] ?? null;
        $this->vars['plan'] = collect(\Aero\Sites\Classes\SignupPlans::all())->firstWhere('is_pro', true);
    }
}
