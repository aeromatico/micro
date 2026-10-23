<?php

use Aero\Church\Classes\PortalAccess;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Resource;
use Aero\Sites\Models\Tenant;
use Illuminate\Support\Facades\Route;

/**
 * Descarga de recursos. El archivo es privado: solo se sirve aquí, tras comprobar que el sitio de la iglesia
 * del dominio está activo, que el recurso está publicado y que la visibilidad lo permite (público o miembro).
 */
Route::middleware('web')->get('iglesia/recurso/{id}', function (int $id) {
    $tenant = Tenant::resolveFromDomain(request()->getHost());
    abort_unless($tenant && ChurchSettings::where('tenant_id', $tenant->id)->value('site_enabled'), 404);

    $isMember = PortalAccess::isMember(\Auth::user(), $tenant->id);
    $res = Resource::where('tenant_id', $tenant->id)->shown($isMember)->find($id);
    abort_unless($res && $res->file, 404);

    $file = $res->file;

    return response()->streamDownload(function () use ($file) {
        echo $file->getContents();
    }, $file->file_name, ['Content-Type' => $file->content_type, 'X-Content-Type-Options' => 'nosniff']);
})->whereNumber('id');

/**
 * API REST (api/v1/church). Solo si Aero.Api está instalado: el middleware `aero.api` autentica la key y
 * fija el negocio dueño; una key sin negocio no ve nada.
 */
if (class_exists(\Aero\Api\Classes\EndpointRegistry::class)) {
    Route::prefix('api/v1/church')->group(function () {
        Route::middleware('aero.api:church.members.read')->group(function () {
            Route::get('members', [\Aero\Church\Http\Controllers\Api\MembersController::class, 'index']);
            Route::get('members/{id}', [\Aero\Church\Http\Controllers\Api\MembersController::class, 'show'])->whereNumber('id');
        });
        Route::middleware('aero.api:church.events.read')->group(function () {
            Route::get('events', [\Aero\Church\Http\Controllers\Api\EventsController::class, 'index']);
            Route::get('events/{id}', [\Aero\Church\Http\Controllers\Api\EventsController::class, 'show'])->whereNumber('id');
        });
        Route::middleware('aero.api:church.visits.read')->get('visits', [\Aero\Church\Http\Controllers\Api\VisitsController::class, 'index']);
        Route::middleware('aero.api:church.visits.write')->post('visits', [\Aero\Church\Http\Controllers\Api\VisitsController::class, 'store']);
    });
}
