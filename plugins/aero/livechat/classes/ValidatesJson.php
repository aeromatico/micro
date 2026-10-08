<?php namespace Aero\Livechat\Classes;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Validator;

/**
 * `$request->validate()` deja el error en manos del manejador de excepciones
 * de October, que devuelve su página HTML de error en vez de JSON 422.
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
