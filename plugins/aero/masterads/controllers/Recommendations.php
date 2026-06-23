<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Aero\MasterAds\Models\Recommendation;
use Aero\MasterAds\Jobs\ApplyRecommendationJob;
use BackendAuth;
use Flash;
use Redirect;

/**
 * Recommendations — Backend controller for human review of AI suggestions.
 *
 * Surfaces the moderation queue produced by `RecommendationEngine` and lets
 * authorized reviewers approve, reject, or hand-fire the
 * `ApplyRecommendationJob` for an already-approved recommendation. The
 * controller is intentionally thin: it persists the lifecycle transitions
 * (pending -> approved/rejected) directly on the model and delegates the
 * actual push-to-Meta work to the queued applier so the request returns
 * immediately.
 *
 * Behaviors:
 *  - FormController  — preview/update screens driven by `config_form.yaml`
 *                      and `models/recommendation/fields.yaml`.
 *  - ListController  — moderation queue driven by `config_list.yaml`,
 *                      `models/recommendation/columns.yaml` and
 *                      `config_filter.yaml`.
 *
 * Permissions:
 *  - `aero.masterads.review_recommendations` — gates the controller as a
 *    whole and is enforced again on `onApprove` / `onReject` so a stale
 *    session can't slip a state mutation through (Requirement 12.2, 12.6).
 *  - `aero.masterads.apply_recommendations` — additionally required for the
 *    `onApplyNow` action that dispatches `ApplyRecommendationJob`.
 *
 * Navigation context: registered under the `Aero.MasterAds` plugin with the
 * top-level `masterads` menu and the `recommendations` side-menu entry
 * (see `Plugin::registerNavigation()`).
 *
 * Validates: Requirements 7.1, 8.4, 10.2, 12.2, 12.6, 17.2, 20.1
 */
class Recommendations extends Controller
{
    /**
     * Behaviors implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * Form behavior configuration (relative to the controller's
     * `recommendations/` view directory).
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List behavior configuration.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Baseline permission required to access any action on this controller.
     * The mutation handlers below re-check this (and the apply permission)
     * defensively before persisting any state change.
     */
    public $requiredPermissions = ['aero.masterads.review_recommendations'];

    /**
     * Activate the "recommendations" side-menu entry whenever this
     * controller renders, so the navigation reflects the current section.
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'recommendations');
    }

    /**
     * Approve a pending recommendation from the preview screen.
     *
     * Transitions the recommendation to `approved` so it becomes eligible
     * for `onApplyNow` (manual) or the auto-apply path triggered by the
     * `RecommendationObserver`. The permission check is repeated here so a
     * user whose role was downgraded mid-session cannot still mutate state
     * via a stale preview page.
     *
     * @param  int|string $recordId Recommendation primary key.
     * @return mixed                Redirect response refreshing the page.
     */
    public function preview_onApprove($recordId)
    {
        if (!BackendAuth::userHasAccess('aero.masterads.review_recommendations')) {
            return response('Forbidden', 403);
        }
        $rec = Recommendation::findOrFail($recordId);
        $rec->status = 'approved';
        $rec->save();
        Flash::success('Recomendación aprobada.');
        return Redirect::refresh();
    }

    /**
     * Reject a pending recommendation from the preview screen.
     *
     * Marks the recommendation as `rejected` so it stops appearing in the
     * actionable queue. Permission is re-validated for the same reason as
     * `preview_onApprove`.
     *
     * @param  int|string $recordId Recommendation primary key.
     * @return mixed                Redirect response refreshing the page.
     */
    public function preview_onReject($recordId)
    {
        if (!BackendAuth::userHasAccess('aero.masterads.review_recommendations')) {
            return response('Forbidden', 403);
        }
        $rec = Recommendation::findOrFail($recordId);
        $rec->status = 'rejected';
        $rec->save();
        Flash::success('Recomendación rechazada.');
        return Redirect::refresh();
    }

    /**
     * Hand-fire `ApplyRecommendationJob` for an approved recommendation.
     *
     * Requires the dedicated `aero.masterads.apply_recommendations`
     * permission on top of the controller's baseline review permission, and
     * refuses to dispatch anything unless the recommendation is in the
     * `approved` state — applying directly from `pending` would skip the
     * human gate required by Requirement 8.4. The job is queued (not run
     * inline) so the response returns immediately and the Meta Graph API
     * call happens on the Redis worker (Requirement 17.2).
     *
     * @param  int|string $recordId Recommendation primary key.
     * @return mixed                Redirect response refreshing the page.
     */
    public function preview_onApplyNow($recordId)
    {
        if (!BackendAuth::userHasAccess('aero.masterads.apply_recommendations')) {
            return response('Forbidden', 403);
        }
        $rec = Recommendation::findOrFail($recordId);
        if ($rec->status !== 'approved') {
            Flash::error('La recomendación debe estar aprobada antes de aplicarse.');
            return Redirect::refresh();
        }
        $user = BackendAuth::getUser();
        ApplyRecommendationJob::dispatch($rec->id, $user->id);
        Flash::success('Aplicación encolada — Master Ads ejecutará el cambio en Meta.');
        return Redirect::refresh();
    }
}
