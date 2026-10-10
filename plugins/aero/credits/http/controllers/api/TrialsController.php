<?php namespace Aero\Credits\Http\Controllers\Api;

use Aero\Credits\Classes\PartnerTrials;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Trials para clientes de un sistema asociado. Solo keys de la PLATAFORMA
 * (sin dueño tenant): un tenant no puede emitir cupones de plan. La referencia
 * del cliente queda ligada a la key que la emitió (source = key:{id}), de modo
 * que dos sistemas distintos no pisan ni leen las referencias del otro.
 */
class TrialsController extends Controller
{
    protected const REF_RULE = ['required', 'string', 'min:1', 'max:120', 'regex:/^[A-Za-z0-9._:@\-]+$/'];

    public function store(Request $request)
    {
        if ($denied = $this->platformOnly($request)) {
            return $denied;
        }

        $validator = validator($request->all(), [
            'ref'  => self::REF_RULE,
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $data = $validator->validated();

        try {
            $result = PartnerTrials::issue($this->source($request), $data['ref'], $data['name'] ?? null);
        }
        catch (\RuntimeException $e) {
            \Log::error('Aero.Credits: ' . $e->getMessage());

            return response()->json(['error' => 'trial_unavailable', 'message' => 'El Trial no está disponible en este momento.'], 503);
        }

        return response()->json(['data' => $result], $result['created'] ? 201 : 200);
    }

    public function show(Request $request, string $ref)
    {
        if ($denied = $this->platformOnly($request)) {
            return $denied;
        }

        $validator = validator(['ref' => $ref], ['ref' => self::REF_RULE]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $result = PartnerTrials::status($this->source($request), $ref);
        if (!$result) {
            return response()->json(['error' => 'not_found', 'message' => 'Sin Trial emitido para esa referencia.'], 404);
        }

        return response()->json(['data' => $result]);
    }

    /** Siempre JSON: bajo October una ValidationException se renderiza como página de error. */
    protected function invalid($validator)
    {
        return response()->json(['error' => 'invalid_request', 'message' => 'Datos inválidos.', 'errors' => $validator->errors()], 422);
    }

    protected function source(Request $request): string
    {
        return 'key:' . $request->attributes->get('api_key')->id;
    }

    /** Falla cerrado: sin key o con key de tenant, no se emite nada. */
    protected function platformOnly(Request $request)
    {
        $key = $request->attributes->get('api_key');

        if (!$key || $key->owner_type) {
            return response()->json(['error' => 'forbidden', 'message' => 'Esta operación es solo para keys de la plataforma.'], 403);
        }

        return null;
    }
}
