<?php

use Aero\Oauth\Classes\OauthFlow;
use Aero\Oauth\Classes\Providers\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// `web` aporta sesión (state/PKCE) y cookies; el flujo valida todo por su cuenta.
Route::middleware(['web', 'throttle:20,1'])->prefix('aero/oauth')->group(function () {
    Route::get('{provider}/redirect', function (Request $request, string $provider) {
        abort_unless($p = ProviderRegistry::get($provider), 404);

        return OauthFlow::begin($request, $p);
    });

    Route::get('{provider}/callback', function (Request $request, string $provider) {
        abort_unless($p = ProviderRegistry::get($provider), 404);

        return OauthFlow::callback($request, $p);
    });
});
