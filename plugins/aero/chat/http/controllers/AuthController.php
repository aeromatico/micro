<?php namespace Aero\Chat\Http\Controllers;

use Aero\Chat\Models\ChatToken;
use Aero\Sites\Models\Tenant;
use Backend\Models\User;
use BackendAuth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    use \Aero\Chat\Classes\ValidatesJson;

    /** GET tenants/{handle} — para mostrar el nombre en el login y validar el slug. */
    public function tenant(string $handle)
    {
        $tenant = $this->findTenant($handle);

        if (!$tenant) {
            return response()->json(['error' => 'tenant_not_found', 'message' => 'No existe ese espacio.'], 404);
        }

        return response()->json(['data' => ['handle' => $tenant->handle, 'name' => $tenant->name]]);
    }

    /** POST tenants/{handle}/login */
    public function login(Request $request, string $handle)
    {
        $tenant = $this->findTenant($handle);

        if (!$tenant) {
            return response()->json(['error' => 'tenant_not_found', 'message' => 'No existe ese espacio.'], 404);
        }

        $data = $this->check($request, ['login' => 'required|string|max:190', 'password' => 'required|string|max:190']);

        // Un solo mensaje para credenciales malas y para "sin acceso a este
        // tenant": no revela si el usuario existe.
        $denied = response()->json(['error' => 'invalid_credentials', 'message' => 'Usuario o contraseña incorrectos.'], 422);

        try {
            $user = BackendAuth::findUserByCredentials(['login' => $data['login'], 'password' => $data['password']]);
        } catch (\Throwable $e) {
            return $denied;
        }

        if (!$user || !$user->is_activated || !$tenant->isAccessibleBy($user)) {
            return $denied;
        }

        if (\Aero\Sites\Classes\ProFeatures::blocks($user, 'aero/chat/pwa')) {
            return response()->json(['error' => 'pro_required', 'message' => 'El chat es parte del plan PRO.'], 403);
        }

        $token = ChatToken::issue($tenant->id, $user->id, $request->userAgent());

        return response()->json(['data' => [
            'token'  => $token,
            'user'   => $this->user($user),
            'tenant' => $this->tenantPayload($tenant),
        ]]);
    }

    /** GET me */
    public function me(Request $request)
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json(['data' => [
            'user'   => $this->user($request->attributes->get('chat_user')),
            'tenant' => $this->tenantPayload($tenant),
        ]]);
    }

    /** POST logout — invalida solo el token de este dispositivo. */
    public function logout(Request $request)
    {
        $request->attributes->get('chat_token')->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    protected function findTenant(string $handle): ?Tenant
    {
        return Tenant::where('handle', strtolower($handle))->where('status', 'active')->first();
    }

    /**
     * Incluye la tarifa por mensaje (Aero.Credits, dependencia blanda) para
     * que el PWA la muestre en el composer sin una llamada aparte. null si
     * el plugin no está instalado o el envío por este canal no cuesta nada.
     */
    protected function tenantPayload(Tenant $tenant): array
    {
        return [
            'handle'  => $tenant->handle,
            'name'    => $tenant->name,
            'credits' => \Aero\Hello\Classes\ApiCredits::costHintFor($tenant->id),
        ];
    }

    public static function user(User $user): array
    {
        $name = trim($user->first_name . ' ' . $user->last_name) ?: $user->login;

        return [
            'id'       => $user->id,
            'name'     => $name,
            'initials' => mb_strtoupper(collect(preg_split('/\s+/', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')),
        ];
    }
}
