<?php namespace Aero\Pos\Http\Middleware;

use Aero\Pos\Models\Cashier;
use Aero\Pos\Models\DeviceToken;
use Aero\Sites\Models\Tenant;
use Backend\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentica al cajero por su token y revalida en cada petición que siga
 * activo y con acceso al tenant. Deja en `$request->attributes`:
 * `pos_user`, `pos_cashier`, `tenant`, `tenant_id`, `pos_token`.
 */
class AuthenticatePosToken
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();
        $token = $plain ? DeviceToken::where('token_hash', hash('sha256', $plain))->where('expires_at', '>', now())->first() : null;

        if (!$token) {
            return $this->fail('unauthenticated', 'Sesión no válida o vencida.', 401);
        }

        $user = User::find($token->user_id);
        $tenant = Tenant::find($token->tenant_id);
        $cashier = $tenant ? Cashier::forTenant($tenant->id)->where('user_id', $token->user_id)->where('is_active', true)->first() : null;

        if (!$user || !$user->is_activated || !$tenant || $tenant->status !== 'active' || !$cashier || !$tenant->isAccessibleBy($user)) {
            $token->delete();
            return $this->fail('unauthenticated', 'Tu acceso a este punto de venta ya no está vigente.', 401);
        }

        if (\Aero\Sites\Classes\ProFeatures::blocks($user, 'aero/pos/app')) {
            return $this->fail('pro_required', 'El punto de venta es parte del plan PRO.', 403);
        }

        if (!$token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now(), 'expires_at' => now()->addDays(30)])->save();
        }

        $request->attributes->set('pos_token', $token);
        $request->attributes->set('pos_user', $user);
        $request->attributes->set('pos_cashier', $cashier);
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    protected function fail(string $code, string $message, int $status)
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
