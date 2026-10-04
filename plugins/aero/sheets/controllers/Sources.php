<?php namespace Aero\Sheets\Controllers;

use Aero\Sheets\Classes\SourceRegistry;
use Backend\Classes\Controller;
use BackendMenu;

/** Solo superadmin: qué modelos y qué campos se pueden sincronizar. */
class Sources extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sheets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sheets', 'sheets', 'sources');
    }

    /** Traduce la tabla de campos del formulario al JSON de la fuente. */
    public function formBeforeSave($model): void
    {
        $posted = (array) post('sync_fields', []);
        if (!$posted || !$model->model_class) {
            return;
        }

        $candidates = SourceRegistry::candidateFields($model->model_class);
        $fields = [];
        foreach ($posted as $key => $row) {
            if (!isset($candidates[$key]) || (empty($row['import']) && empty($row['export']))) {
                continue; // solo campos que existen, no vetados y marcados
            }
            $fields[] = [
                'key'    => $key,
                'label'  => trim((string) ($row['label'] ?? '')) ?: $candidates[$key]['label'],
                'type'   => $candidates[$key]['type'],
                'export' => !empty($row['export']),
                // Los campos de sistema (id, tenant_id, fechas) solo se exportan.
                'import' => !empty($row['import']) && !$candidates[$key]['readonly'],
            ];
        }
        $model->fields = $fields;
    }
}
