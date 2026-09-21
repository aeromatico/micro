<?php namespace Aero\Tracking\Http\Controllers\Api;

use Aero\Tracking\Classes\TrackingService;
use Aero\Tracking\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

/**
 * Receptor de OwnTracks en modo HTTP. La app espera un array JSON como
 * respuesta y reintenta ante cualquier no-2xx, así que solo un token
 * inválido devuelve error; lo demás se ignora con `[]`.
 */
class IngestController extends Controller
{
    public function owntracks(Request $request, string $token)
    {
        $asset = Asset::where('ingest_token', $token)->where('is_active', true)->first();

        if (!$asset) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $p = $request->json()->all();

        if (($p['_type'] ?? null) !== 'location' || !isset($p['lat'], $p['lon'])) {
            return response()->json([]);
        }

        try {
            TrackingService::recordPosition($asset, [
                'lat'         => $p['lat'],
                'lng'         => $p['lon'],
                'recorded_at' => $p['tst'] ?? null,
                'speed'       => isset($p['vel']) ? $p['vel'] / 3.6 : null,  // OwnTracks manda km/h
                'heading'     => $p['cog'] ?? null,
                'accuracy'    => $p['acc'] ?? null,
                'altitude'    => $p['alt'] ?? null,
                'battery'     => $p['batt'] ?? null,
            ]);
        }
        catch (InvalidArgumentException) {
            // Punto descartado (coordenadas inválidas); no debe hacer reintentar al dispositivo.
        }

        return response()->json([]);
    }
}
