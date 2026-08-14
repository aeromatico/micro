<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Jobs\SyncMetaAccountJob;
use Flash;
use Redirect;
use Session;

/**
 * MetaAccounts — backend controller for managing connected Meta ad accounts.
 *
 * Responsibilities:
 *  - List / form (CRUD) over `MetaAccount` model via OctoberCMS behaviours.
 *  - Kick off the Meta OAuth handshake (`connect()`): stashes the chosen
 *    workspace in the session so the OAuth callback in `routes.php` can
 *    bind the resulting tokens to the right tenant (Requirement 2.1).
 *  - AJAX action `onSyncNow` dispatches `SyncMetaAccountJob` to pull fresh
 *    campaigns/insights for the loaded record (Requirements 3.8, 11.2).
 *  - AJAX action `onRefreshToken` runs `MetaTokenRefresher` synchronously
 *    for operator-driven token rotation (Requirements 9.5, 17.2).
 *
 * Access is gated by the `aero.masterads.manage_meta_accounts` permission
 * (Requirements 12.2, 12.6, 20.1). Both AJAX actions return a Redirect to
 * refresh the page so OctoberCMS shows the resulting Flash message.
 *
 * Validates: Requirements 2.1, 3.8, 9.5, 10.2, 11.2, 12.2, 12.6, 17.2, 20.1
 */
class MetaAccounts extends Controller
{
    /**
     * @var array implement enables FormController + ListController behaviours.
     * FormController drives create/update/preview; ListController drives the
     * index list and toolbar search.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /** @var string formConfig path resolved relative to this controller. */
    public $formConfig = 'config_form.yaml';

    /** @var string listConfig path resolved relative to this controller. */
    public $listConfig = 'config_list.yaml';

    /**
     * @var array requiredPermissions — every action in this controller
     * requires `manage_meta_accounts` (Requirements 12.2, 12.6).
     */
    public $requiredPermissions = ['aero.masterads.manage_meta_accounts'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'metaaccounts');
    }

    /**
     * Initiates OAuth flow: stashes workspace_id in session and redirects
     * the browser to Meta's authorization URL.
     *
     * The chosen `workspace_id` is persisted in the session under
     * `aero.masterads.connecting_workspace`. The OAuth callback handler in
     * `routes.php` reads it back to bind the resulting Meta tokens to the
     * right tenant — this is critical because the callback request from
     * Facebook arrives without any tenant context of its own.
     *
     * Validates: Requirements 2.1, 17.2
     */
    public function connect($workspaceId = null)
    {
        $workspaceId = (int) ($workspaceId ?? get('workspace_id'));
        if ($workspaceId <= 0) {
            Flash::error('Selecciona primero un workspace para conectar Meta.');
            return Redirect::to(\Backend::url('aero/masterads/metaaccounts'));
        }

        Session::put('aero.masterads.connecting_workspace', $workspaceId);

        $appId    = config('services.master_ads_meta.app_id');
        $redirect = config('services.master_ads_meta.redirect');
        $scopes   = 'ads_management,ads_read,business_management';

        $url = 'https://www.facebook.com/v19.0/dialog/oauth?'
            . http_build_query([
                'client_id'     => $appId,
                'redirect_uri'  => $redirect,
                'scope'         => $scopes,
                'response_type' => 'code',
                'state'         => csrf_token(),
            ]);

        return Redirect::to($url);
    }

    /**
     * AJAX handler: dispatches `SyncMetaAccountJob` for the loaded record.
     *
     * Called from the preview/update page via the "Sincronizar ahora" button.
     * The job runs asynchronously on the queue (Requirement 11.2) so we
     * return immediately with a flash confirmation.
     *
     * Validates: Requirements 3.8, 11.2
     */
    public function preview_onSyncNow($recordId)
    {
        $account = MetaAccount::findOrFail($recordId);
        SyncMetaAccountJob::dispatch($account);
        Flash::success('Sincronización encolada para la cuenta ' . $account->meta_act_id);
        return Redirect::refresh();
    }

    /**
     * AJAX handler: refresh the Meta access_token for the loaded record now.
     *
     * Runs `MetaTokenRefresher` synchronously rather than via a job, since
     * the operator typically wants immediate feedback. Wrapped in a broad
     * try/catch so any RuntimeException (network failure, Meta rejecting
     * the refresh, etc.) surfaces as a friendly flash message instead of
     * a 500 page.
     *
     * Validates: Requirements 9.5, 17.2
     */
    public function preview_onRefreshToken($recordId)
    {
        $account = MetaAccount::findOrFail($recordId);

        try {
            app(\Aero\MasterAds\Classes\Meta\MetaTokenRefresher::class)->refresh($account);
            Flash::success('Token de Meta refrescado correctamente.');
        } catch (\Throwable $e) {
            Flash::error('No se pudo refrescar el token: ' . $e->getMessage());
        }

        return Redirect::refresh();
    }
}
