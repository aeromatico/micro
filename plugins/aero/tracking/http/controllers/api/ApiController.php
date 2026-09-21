<?php namespace Aero\Tracking\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class ApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            return $this->tenantId($request)
                ? $next($request)
                : $this->error('tenant_missing', 'La API key debe pertenecer a un tenant.', 403);
        });
    }

    /** Tenant dueño de la key autenticada; sin él no hay datos que mostrar. */
    protected function tenantId(Request $request): ?int
    {
        $key = $request->attributes->get('api_key');

        return $key && class_exists(\Aero\Sites\Models\Tenant::class) && $key->owner_type === \Aero\Sites\Models\Tenant::class
            ? (int) $key->owner_id
            : null;
    }

    protected function apiKeyId(Request $request): ?int
    {
        return $request->attributes->get('api_key')?->id;
    }

    protected function error(string $code, string $message, int $status, array $extra = [])
    {
        return response()->json(['error' => $code, 'message' => $message] + $extra, $status);
    }

    protected function notFound(string $what)
    {
        return $this->error('not_found', "{$what} no encontrado.", 404);
    }

    protected function paginated($page, callable $present)
    {
        return response()->json([
            'data' => $page->getCollection()->map($present)->values(),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }
}
