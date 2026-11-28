<?php

use Aero\Services\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

// Público y de solo lectura (mismos datos que el megamenú); throttle por IP.
Route::get('api/v1/services/menu', [MenuController::class, 'index'])->middleware('throttle:60,1');

// Botón «Comprar» de /store. La sesión del panel es propia de cada dominio, así que
// se revisa la del actual y, si no hay, la de master antes de mandar a los planes.
Route::get('store/comprar', function () {
    if (\BackendAuth::check()) {
        $to = url(\Backend::uri() . '/aero/services/myservices');
    }
    elseif (request()->getHost() !== 'master.market.com.bo') {
        $to = 'https://master.market.com.bo/store/comprar';
    }
    else {
        $to = 'https://market.com.bo/#precios';
    }

    return redirect()->away($to)->header('Cache-Control', 'no-store, private');
})->middleware(['web', 'throttle:60,1']);
