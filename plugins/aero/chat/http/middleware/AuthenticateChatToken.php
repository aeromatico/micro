<?php namespace Aero\Chat\Http\Middleware;

use Aero\Chat\Models\ChatToken;
use Aero\Sites\Models\Tenant;
use Backend\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentica al agente por su token y revalida en cada petición que siga
 * activo y con acceso al tenant: quitarle el acceso lo saca al instante.
 * Deja en `$request->attributes`: `chat_user`, `tenant`, `tenant_id`, `chat_token`.
 */
class AuthenticateChatToken
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();
        $token = $plain ? ChatToken::where('token_hash', ChatToken::hash($plain))->where('expires_at', '>', now())->first() : null;

        if (!$token) {
            return $this->fail('unauthenticated', 'Sesión no válida o vencida.');
        }

        $user = User::find($token->user_id);
        $tenant = Tenant::find($token->tenant_id);

        if (!$user || !$user->is_activated || !$tenant || $tenant->status !== 'active' || !$tenant->isAccessibleBy($user)) {
            $token->delete();
            return $this->fail('unauthenticated', 'Tu acceso a este tenant ya no está vigente.');
        }

        if (!$token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('chat_token', $token);
        $request->attributes->set('chat_user', $user);
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    protected function fail(string $code, string $message)
    {
        return response()->json(['error' => $code, 'message' => $message], 401);
    }
}
