<?php namespace Aero\Sites\Http\Middleware;

use Aero\Sites\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class ResolveTenantFromDomain
{
    public function handle(Request $request, Closure $next): mixed
    {
        $host = $request->getHost();
        $tenant = Tenant::resolveFromDomain($host);

        if (!$tenant || $tenant->status !== 'active') {
            return response()->json(['error' => 'tenant_not_found', 'message' => 'Tenant not found or inactive.'], 404);
        }

        $request->attributes->set('tenant', $tenant);
        return $next($request);
    }
}
