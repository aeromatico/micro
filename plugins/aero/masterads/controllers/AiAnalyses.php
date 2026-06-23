<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * AiAnalyses — Backend CRUD controller for AI analysis runs.
 *
 * Surfaces the {@see \Aero\MasterAds\Models\AiAnalysis} list and preview
 * screens to backend users so they can inspect every analysis dispatched
 * against a target (campaign|adset|ad): status, tokens consumed, cost,
 * the captured `prompt_payload`, the provider's `raw_response` and the
 * `metrics_snapshot` that fed the prompt. Reproducibility of an analysis
 * (Requirement 8.5) depends on this audit UI surfacing the full record.
 *
 * Behaviors:
 *  - FormController — preview/update screens, driven by
 *                     `config_form.yaml` and `models/aianalysis/fields.yaml`.
 *  - ListController — index list, driven by `config_list.yaml` and
 *                     `models/aianalysis/columns.yaml`, with status /
 *                     target_type / provider scopes defined in
 *                     `config_filter.yaml`.
 *
 * Navigation context: side-menu entry `aianalyses` under the `masterads`
 * top-level menu (see `Plugin::registerNavigation()`).
 *
 * Authorization: gated by `aero.masterads.run_analysis` — the same
 * permission required to enqueue a new analysis. Tenant isolation is
 * enforced at the model layer via the `BelongsToTenantScope` global scope
 * on AiAnalysis (Requirements 10.x).
 *
 * Validates: Requirements 6.10, 8.5, 16.2, 16.3, 17.2, 20.1
 */
class AiAnalyses extends Controller
{
    /**
     * Behaviors implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * Form behavior configuration file (relative to this controller's
     * `aianalyses/` view directory).
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List behavior configuration file.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Permission required to access any action on this controller.
     */
    public $requiredPermissions = ['aero.masterads.run_analysis'];

    /**
     * Bind the active backend menu so the sidebar highlights "Análisis IA"
     * when any action of this controller is rendered.
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'aianalyses');
    }
}
