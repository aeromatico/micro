<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Jobs\RunAiAnalysisJob;
use BackendAuth;
use Flash;
use Redirect;

/**
 * Campaigns — Backend CRUD controller for Meta campaign records.
 *
 * Exposes the read-mostly list and preview screens of Meta campaigns ingested
 * by `SyncInsightsJob` / `SyncMetaAccountJob`. Campaigns are not authored in
 * this UI (creation/mutation lives in Meta Ads Manager); the screens here
 * are the launching pad for AI analysis and recommendation review.
 *
 * Behaviors:
 *  - FormController — preview/update screens, driven by `config_form.yaml`
 *                     and `models/campaign/fields.yaml`.
 *  - ListController — index list with filters, driven by `config_list.yaml`,
 *                     `config_filter.yaml`, and `models/campaign/columns.yaml`.
 *
 * AJAX actions:
 *  - `preview_onAnalyzeNow($recordId)` / `update_onAnalyzeNow($recordId)` —
 *    Enqueues a `RunAiAnalysisJob` for the target campaign. The job runs
 *    asynchronously on the queue worker (Requirement 16.1) and the user is
 *    redirected back with a success flash message. The action is gated by
 *    the `aero.masterads.run_analysis` permission, evaluated at call time
 *    (Requirement 12.6); see `Plugin::registerPermissions()`.
 *
 * Permissions:
 *  - Controller access: `aero.masterads.access_campaigns` (Requirement 12.2).
 *  - Analyze action:    `aero.masterads.run_analysis` (Requirement 12.6).
 *
 * Validates: Requirements 6.1, 10.2, 12.2, 12.6, 17.2, 20.1.
 */
class Campaigns extends Controller
{
    /**
     * Behaviors implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * Form behavior configuration file (relative to the controller's
     * `campaigns/` view directory).
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List behavior configuration file.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Permission required to reach any action on this controller.
     */
    public $requiredPermissions = ['aero.masterads.access_campaigns'];

    /**
     * Bind the active backend menu so the sidebar highlights "Campaigns".
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'campaigns');
    }

    /**
     * AJAX handler — dispatches `RunAiAnalysisJob` for the campaign whose
     * id is `$recordId`. Returns a 403 response when the active user lacks
     * the `aero.masterads.run_analysis` permission; otherwise enqueues the
     * job, flashes a success message, and refreshes the preview screen.
     *
     * @param  int|string $recordId  Campaign primary key from the URL.
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function preview_onAnalyzeNow($recordId)
    {
        if (!BackendAuth::userHasAccess('aero.masterads.run_analysis')) {
            return response('Forbidden', 403);
        }

        $campaign = Campaign::findOrFail($recordId);
        $user = BackendAuth::getUser();

        RunAiAnalysisJob::dispatch('campaign', $campaign->id, [
            'lookback_days' => 14,
            'triggered_by' => $user?->id,
        ]);

        Flash::success('Análisis IA encolado para la campaña: ' . $campaign->name);
        return Redirect::refresh();
    }

    /**
     * Same as {@see preview_onAnalyzeNow()}, surfaced on the update form so
     * the analyze action is reachable from both the preview and the edit
     * screens without duplicating logic.
     *
     * @param  int|string $recordId
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function update_onAnalyzeNow($recordId)
    {
        return $this->preview_onAnalyzeNow($recordId);
    }
}
