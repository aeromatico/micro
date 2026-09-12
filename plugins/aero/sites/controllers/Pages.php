<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;

class Pages extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $formConfig    = 'config_form.yaml';
    public $listConfig    = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['aero.sites.manage_pages'];

    public function __construct()
    {
        parent::__construct();
        $this->setSitesMenuContext('pages', 'paginas');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);

        // Inicio (slug vacío) tiene su propio flujo especializado de diseño +
        // generación con IA en Contenidos — no se edita desde acá, para no
        // duplicar el punto de entrada.
        $query->where('slug', '!=', '');
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
        }
    }

    public function formBeforeSave($model): void
    {
        $data = post('Page', []);
        $mode = $data['content_mode'] ?? $model->content_mode ?? 'puck';

        if ($mode === 'richeditor') {
            $model->content = $data['content_richeditor'] ?? '';
        } elseif ($mode === 'code') {
            $model->content = $data['content_raw'] ?? '';
        } elseif (array_key_exists('content', $data)) {
            // Puck mode: HTML is emitted by PuckEditor widget as a hidden
            // textarea outside of getSaveData(), so we capture it from POST.
            $model->content = $data['content'];
        }
    }
}
