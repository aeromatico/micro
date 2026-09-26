<?php namespace Aero\Hub\Controllers;

use Aero\Hub\Models\HubEndpoint;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

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

    /**
     * "Aplicar ganancia" masivo: recalcula `credit_cost` de TODOS los
     * endpoints con costo real conocido (`reference_cost_usd`) usando su
     * margen efectivo (propio si lo cargó, si no el default global de
     * Aero\Hub\Models\Settings — ver HubEndpoint::effectiveMargin()). Es la
     * forma de aplicar un cambio en el margen global a lo ya existente sin
     * editar fila por fila; respeta el filtro de división activo (Modelos
     * IA / APIs) para poder aplicar solo a una de las dos si se quiere.
     */
    public function onRecalculateCosts()
    {
        $query = HubEndpoint::whereNotNull('reference_cost_usd')->whereNotNull('credit_type_id');

        if ($division = get('HubEndpoints-division')) {
            $query->where('division', $division);
        }

        $updated = 0;
        $query->chunkById(200, function ($endpoints) use (&$updated) {
            foreach ($endpoints as $endpoint) {
                $before = $endpoint->credit_cost;
                $endpoint->recalculateCreditCost();

                if ($endpoint->isDirty('credit_cost')) {
                    $endpoint->save();
                }

                if ($endpoint->credit_cost !== $before) {
                    $updated++;
                }
            }
        });

        Flash::success(trans('aero.hub::lang.endpoint.recalculate_done', ['count' => $updated]));

        return $this->listRefresh();
    }
}
