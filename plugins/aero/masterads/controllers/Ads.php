<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Aero\MasterAds\Models\Ad;
use Aero\MasterAds\Jobs\RunAiAnalysisJob;
use BackendAuth;
use Flash;
use Redirect;

/**
 * Ads — Backend CRUD controller for Meta ad records.
 *
 * Mirrors the read-mostly UX of {@see Campaigns} and {@see AdSets}: ads are
 * ingested from Meta via `SyncInsightsJob` / `SyncMetaAccountJob` rather
 * than authored in this UI. The list and preview screens are the launching
 * point for AI analysis at the individual ad granularity (creative-level
 * recommendations).
 *
 * Behaviors:
 *  - FormController — preview/update screens, driven by `config_form.yaml`
 *                     and `models/ad/fields.yaml`.
 *  - ListController — index list with filters, driven by `config_list.yaml`,
 *                     `config_filter.yaml`, and `models/ad/columns.yaml`.
 *
 * AJAX actions:
 *  - `preview_onAnalyzeNow($recordId)` / `update_onAnalyzeNow($recordId)` —
 *    Enqueues a `RunAiAnalysisJob` for the target ad (`'ad'` target type).
 *    Gated by the `aero.masterads.run_analysis` permission, evaluated at
 *    call time (Requirement 12.6).
 *
 * Permissions:
 *  - Controller access: `aero.masterads.access_campaigns` (Requirement 12.2).
 *  - Analyze action:    `aero.masterads.run_analysis` (Requirement 12.6).
 *
 * Validates: Requirements 6.1, 12.2, 17.2, 20.1.
 */
class Ads extends Controller
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
     * `ads/` view directory).
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List behavior configuration file.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Permission required to reach any action on this controller. Ads
     * share the same access gate as campaigns since they are facets of
     * the same Meta hierarchy.
     */
    public $requiredPermissions = ['aero.masterads.access_campaigns'];

    /**
     * Bind the active backend menu so the sidebar highlights "Ads".
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'ads');
    }

    /**
     * AJAX handler — dispatches `RunAiAnalysisJob` for the ad whose id is
     * `$recordId`. Returns a 403 response when the active user lacks the
     * `aero.masterads.run_analysis` permission; otherwise enqueues the
     * job, flashes a success message, and refreshes the preview screen.
     *
     * @param  int|string $recordId  Ad primary key from the URL.
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function preview_onAnalyzeNow($recordId)
    {
        if (!BackendAuth::userHasAccess('aero.masterads.run_analysis')) {
            return response('Forbidden', 403);
        }

        $ad = Ad::findOrFail($recordId);
        $user = BackendAuth::getUser();

        RunAiAnalysisJob::dispatch('ad', $ad->id, [
            'lookback_days' => 14,
            'triggered_by' => $user?->id,
        ]);

        Flash::success('Análisis IA encolado para el ad: ' . $ad->name);
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
