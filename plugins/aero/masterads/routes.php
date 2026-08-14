<?php

use Aero\MasterAds\Classes\Meta\MetaOAuthService;
use Aero\MasterAds\Classes\Exceptions\MetaOAuthException;

/**
 * Master Ads — OAuth callback for Meta.
 *
 * Receives Meta's redirect with either ?code=... (success) or ?error=...
 * (user cancelled / Meta rejected). On success delegates to
 * MetaOAuthService::exchangeCode and redirects to the new MetaAccount's
 * backend preview page. On error flashes a message and redirects to the
 * MetaAccounts list.
 *
 * The workspace_id is read from the session key `aero.masterads.connecting_workspace`
 * which must have been set by the controller that initiated the OAuth flow.
 *
 * Validates: Requirements 2.1, 2.5, 2.8, 2.9, 11.1, 11.2
 */
Route::get('aero/masterads/oauth/meta/callback', function () {
    $error = request('error');
    if ($error) {
        \Flash::error('Meta rechazó la conexión: ' . $error . (request('error_description') ? ' — ' . request('error_description') : ''));
        return \Redirect::to(\Backend::url('aero/masterads/metaaccounts'));
    }

    $code = (string) request('code', '');
    $workspaceId = (int) session('aero.masterads.connecting_workspace', 0);

    if ($code === '' || $workspaceId <= 0) {
        \Flash::error('Faltan parámetros del callback OAuth (code o workspace).');
        return \Redirect::to(\Backend::url('aero/masterads/metaaccounts'));
    }

    try {
        /** @var MetaOAuthService $service */
        $service = app(MetaOAuthService::class);
        $metaAccount = $service->exchangeCode($code, $workspaceId);

        \Flash::success('Cuenta Meta conectada: ' . ($metaAccount->name ?: $metaAccount->meta_act_id));
        return \Redirect::to(\Backend::url('aero/masterads/metaaccounts/preview/' . $metaAccount->id));
    } catch (MetaOAuthException $e) {
        \Log::warning('[MasterAds] OAuth exchange failed', ['error' => $e->getMessage(), 'context' => $e->context]);
        \Flash::error('No se pudo completar la conexión con Meta. Revisa el log del backend.');
        return \Redirect::to(\Backend::url('aero/masterads/metaaccounts'));
    }
});
