<?php namespace Aero\Pos\Http\Controllers;

use Aero\Pos\Models\Cashier;
use Aero\Pos\Models\DeviceToken;
use Aero\Sites\Models\Tenant;
use Backend\Models\User;
use BackendAuth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** GET tenants/{handle} — nombre para la pantalla de acceso. */
    public function tenant(string $handle)
    {
        $tenant = $this->findTenant($handle);

        return $tenant
            ? response()->json(['data' => ['handle' => $tenant->handle, 'name' => $tenant->name]])
            : response()->json(['error' => 'tenant_not_found', 'message' => 'No existe ese negocio.'], 404);
    }

    /**
     * POST tenants/{handle}/login — `login` + `pin` (4–6 dígitos) o `login` + `password`.
     * Un solo mensaje para cualquier fallo: no revela si el usuario existe.
     */
    public function login(Request $request, string $handle)
    {
        $tenant = $this->findTenant($handle);
        if (!$tenant) {
            return response()->json(['error' => 'tenant_not_found', 'message' => 'No existe ese negocio.'], 404);
        }

        $data = $request->validate(['login' => 'required|string|max:190', 'pin' => 'nullable|string|max:190', 'password' => 'nullable|string|max:190']);
        $denied = response()->json(['error' => 'invalid_credentials', 'message' => 'Usuario o PIN incorrectos.'], 422);

        $user = User::where('login', $data['login'])->orWhere('email', $data['login'])->first();
        $cashier = $user ? Cashier::forTenant($tenant->id)->where('user_id', $user->id)->where('is_active', true)->first() : null;
        if (!$user || !$cashier || !$user->is_activated || !$tenant->isAccessibleBy($user)) {
            return $denied;
        }

        $ok = false;
        if (!empty($data['pin'])) {
            $ok = $cashier->checkPin($data['pin']);
        } elseif (!empty($data['password'])) {
            try {
                $ok = (bool) BackendAuth::findUserByCredentials(['login' => $user->login, 'password' => $data['password']]);
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        if (!$ok) {
            return $denied;
        }

        if (\Aero\Sites\Classes\ProFeatures::blocks($user, 'aero/pos/app')) {
            return response()->json(['error' => 'pro_required', 'message' => 'El punto de venta es parte del plan PRO.'], 403);
        }

        $plain = Str::random(64);
        DeviceToken::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => mb_substr((string) $request->userAgent(), 0, 80),
            'token_hash' => hash('sha256', $plain), 'last_used_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        return response()->json(['data' => ['token' => $plain, 'user' => $this->user($user, $cashier), 'tenant' => ['handle' => $tenant->handle, 'name' => $tenant->name]]]);
    }

    /** POST logout — invalida solo el token de este dispositivo. */
    public function logout(Request $request)
    {
        $request->attributes->get('pos_token')->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    protected function findTenant(string $handle): ?Tenant
    {
        return Tenant::where('handle', strtolower($handle))->where('status', 'active')->first();
    }

    public static function user(User $user, Cashier $cashier): array
    {
        $name = trim($user->first_name . ' ' . $user->last_name) ?: $user->login;

        return [
            'id' => $user->id, 'name' => $name,
            'initials' => mb_strtoupper(collect(preg_split('/\s+/', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')),
            'is_supervisor' => (bool) $cashier->is_supervisor, 'discount_limit_percent' => (int) $cashier->discount_limit_percent,
        ];
    }
}
