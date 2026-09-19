<?php namespace Aero\Chat\Classes;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Validator;

/**
 * Validación que siempre responde JSON 422. `$request->validate()` deja el
 * error en manos del manejador de excepciones de October, que devuelve su
 * página HTML de error (500) en vez de la lista de campos inválidos.
 */
trait ValidatesJson
{
    protected function check(Request $request, array $rules): array
    {
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            throw new HttpResponseException(response()->json([
                'error' => 'validation_failed', 'message' => $v->errors()->first(), 'details' => $v->errors()->toArray(),
            ], 422));
        }

        return $v->validated();
    }
}
