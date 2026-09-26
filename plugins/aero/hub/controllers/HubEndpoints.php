<?php namespace Aero\Hub\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/**
 * Panel de precios del catálogo: las filas las crea/actualiza
 * `Aero\Hub\Classes\CatalogSync` (metadata), acá el superadmin solo fija el
 * color/costo de crédito y activa el endpoint. `?HubEndpoints-division=` filtra
 * por la división Modelos IA / APIs desde el menú lateral.
 */
class HubEndpoints extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.hub.manage_catalog'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Hub', 'hub', 'hubendpoints');
    }

    public function listExtendQuery($query)
    {
        if ($division = get('HubEndpoints-division')) {
            $query->where('division', $division);
        }
    }
}
