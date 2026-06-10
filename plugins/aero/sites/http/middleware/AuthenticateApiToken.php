<?php namespace Aero\Sites\Http\Middleware;

use Aero\Sites\Models\ApiToken;
use Aero\Sites\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'API token required.'], 401);
        }

        $apiToken = ApiToken::findByPlainToken($bearerToken);

        if (!$apiToken) {
            return response()->json(['error' => 'invalid_token', 'message' => 'Invalid API token.'], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json(['error' => 'token_expired', 'message' => 'API token has expired.'], 401);
        }

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        // Token must belong to the resolved tenant
        if ($tenant && $apiToken->tenant_id !== $tenant->id) {
            return response()->json(['error' => 'invalid_token', 'message' => 'Token does not match tenant.'], 401);
        }

        $apiToken->touchLastUsed();
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
