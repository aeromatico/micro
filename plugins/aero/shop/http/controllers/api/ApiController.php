<?php namespace Aero\Shop\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Validator;

abstract class ApiController extends Controller
{
    /** La API de tienda opera siempre sobre la tienda del dueño de la key. */
    protected function tenantId(Request $request): int
    {
        $key = $request->attributes->get('api_key');

        return (int) ($key && $key->owner_type === \Aero\Sites\Models\Tenant::class ? $key->owner_id : 0);
    }

    protected function data($data, int $status = 200, array $meta = [])
    {
        return response()->json($meta ? ['data' => $data, 'meta' => $meta] : ['data' => $data], $status);
    }

    protected function error(string $code, string $message, int $status = 422, array $details = [])
    {
        return response()->json(['error' => $code, 'message' => $message] + ($details ? ['details' => $details] : []), $status);
    }

    protected function paged($page, callable $fn)
    {
        return $this->data(array_map($fn, $page->items()), 200, [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
        ]);
    }

    /** @return array|\Illuminate\Http\JsonResponse */
    protected function validated(Request $request, array $rules)
    {
        $v = Validator::make($request->all(), $rules);

        return $v->fails() ? $this->error('validation_failed', $v->errors()->first(), 422, $v->errors()->toArray()) : $v->validated();
    }
}
