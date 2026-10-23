<?php namespace Aero\Church\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Base de la API de Iglesia: el negocio sale SIEMPRE de la key; nunca de un parámetro. */
abstract class BaseController extends Controller
{
    protected function tenantId(Request $request): ?int
    {
        $key = $request->attributes->get('api_key');

        return $key && ($key->owner_type ?? null) === \Aero\Sites\Models\Tenant::class && $key->owner_id ? (int) $key->owner_id : null;
    }

    protected function noTenant()
    {
        return response()->json(['error' => 'tenant_required', 'message' => 'Esta API key no pertenece a una iglesia: los datos solo se consultan con la key de su iglesia.'], 403);
    }

    protected function notFound(string $what)
    {
        return response()->json(['error' => 'not_found', 'message' => $what . ' no encontrado.'], 404);
    }

    protected function perPage(Request $request, int $default = 50): int
    {
        return max(1, min(200, (int) $request->query('per_page', $default)));
    }

    protected function paginated($page, callable $present)
    {
        return response()->json([
            'data' => collect($page->items())->map($present)->all(),
            'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }
}
