<?php namespace Aero\Oauth\Classes;

use Aero\Oauth\Classes\Providers\ProviderInterface;
use Aero\Oauth\Models\Identity;
use Aero\Oauth\Models\Settings;
use Backend;
use Backend\Models\AccessLog;
use Backend\Models\User as BackendUser;
use BackendAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class OauthFlow
{
    const SESSION_KEY = 'aero_oauth.pending';

    public static function begin(Request $request, ProviderInterface $provider)
    {
        $purpose = $request->query('purpose') === 'connect' ? 'connect' : 'login';

        if ($purpose === 'login' && !Settings::googleLoginEnabled()) {
            return self::fail('El acceso con ' . $provider->label() . ' está desactivado.');
        }
        if ($purpose === 'connect' && !BackendAuth::check()) {
            return redirect(Backend::url('auth/signin'));
        }
        if (!$provider->isConfigured()) {
            return self::fail('Faltan las credenciales de ' . $provider->label() . ' en el servidor.');
        }

        $keys = array_filter(explode(',', (string) $request->query('scopes')));
        $extra = $purpose === 'connect' ? ScopeRegistry::resolve($provider->code(), $keys) : [];
        $verifier = Str::random(64);
        $state = Str::random(40);

        $request->session()->put(self::SESSION_KEY, [
            'state'    => $state,
            'provider' => $provider->code(),
            'purpose'  => $purpose,
            'verifier' => $verifier,
            'user_id'  => $purpose === 'connect' ? BackendAuth::getUser()->id : null,
            'return'   => Oauth::safeReturn($request->query('return'), Backend::url('')),
        ]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return redirect()->away($provider->authorizeUrl(
            $state,
            Providers\ProviderRegistry::callbackUrl($provider->code()),
            array_merge($provider->loginScopes(), $extra),
            $challenge
        ));
    }

    public static function callback(Request $request, ProviderInterface $provider)
    {
        $pending = $request->session()->pull(self::SESSION_KEY);

        if (!$pending
            || $pending['provider'] !== $provider->code()
            || !hash_equals($pending['state'], (string) $request->query('state'))
        ) {
            return self::fail('La sesión de acceso expiró o no es válida. Inténtalo otra vez.');
        }

        $back = $pending['purpose'] === 'connect' ? $pending['return'] : Backend::url('auth/signin');

        if ($request->query('error') || !$request->query('code')) {
            return self::fail('Se canceló el acceso con ' . $provider->label() . '.', $back);
        }

        try {
            $token = $provider->exchangeCode((string) $request->query('code'), Providers\ProviderRegistry::callbackUrl($provider->code()), $pending['verifier']);
            $profile = $provider->profile($token['access_token']);

            return $pending['purpose'] === 'connect'
                ? self::finishConnect($provider, $pending, $token, $profile)
                : self::finishLogin($provider, $token, $profile);
        } catch (RuntimeException $e) {
            return self::fail($e->getMessage(), $back);
        }
    }

    protected static function finishLogin(ProviderInterface $provider, array $token, array $profile)
    {
        $identity = Identity::where('provider', $provider->code())->where('provider_user_id', $profile['id'])->first();
        $user = $identity?->user;

        if (!$user && Settings::linkByVerifiedEmail() && $profile['email_verified'] && $profile['email']) {
            $candidate = BackendUser::where('email', $profile['email'])->first();
            // Solo si esa persona no tiene ya otra cuenta de Google vinculada.
            if ($candidate && !Identity::where('backend_user_id', $candidate->id)->where('provider', $provider->code())->exists()) {
                $user = $candidate;
            }
        }

        if (!$user || !$user->is_activated) {
            return self::fail('Esa cuenta de ' . $provider->label() . ' no está vinculada a ningún usuario. Entra con tu contraseña y vincúlala en «Cuentas conectadas».');
        }

        $identity = self::store($provider, $user->id, $token, $profile, $identity);
        $identity->last_login_at = now();
        $identity->save();

        BackendAuth::login($user, true);
        AccessLog::add($user);

        return redirect()->intended(Backend::url(''));
    }

    protected static function finishConnect(ProviderInterface $provider, array $pending, array $token, array $profile)
    {
        $user = BackendAuth::getUser();

        if (!$user || $user->id !== $pending['user_id']) {
            return self::fail('La sesión cambió durante la conexión. Inténtalo otra vez.', $pending['return']);
        }

        $taken = Identity::where('provider', $provider->code())->where('provider_user_id', $profile['id'])->first();
        if ($taken && $taken->backend_user_id !== $user->id) {
            return self::fail('Esa cuenta de ' . $provider->label() . ' ya está vinculada a otro usuario.', $pending['return']);
        }

        $existing = Oauth::identityFor($user->id, $provider->code());
        if ($existing && $existing->provider_user_id !== $profile['id']) {
            return self::fail('Ya tienes otra cuenta de ' . $provider->label() . ' conectada; desconéctala primero.', $pending['return']);
        }

        self::store($provider, $user->id, $token, $profile, $existing ?? $taken);
        \Flash::success('Cuenta de ' . $provider->label() . ' conectada.');

        return redirect($pending['return']);
    }

    protected static function store(ProviderInterface $provider, int $userId, array $token, array $profile, ?Identity $identity): Identity
    {
        $identity ??= new Identity(['backend_user_id' => $userId, 'provider' => $provider->code(), 'provider_user_id' => $profile['id']]);

        $granted = array_filter(explode(' ', (string) ($token['scope'] ?? '')));
        $identity->fill([
            'email'      => $profile['email'],
            'name'       => $profile['name'],
            'avatar_url' => $profile['avatar'],
        ]);
        $identity->tenant_id ??= CurrentTenant::id();
        // Google devuelve todos los scopes concedidos (include_granted_scopes); se une por si acaso.
        $identity->granted_scopes = array_values(array_unique(array_merge((array) $identity->granted_scopes, $granted)));
        $identity->access_token = $token['access_token'];
        $identity->refresh_token = $token['refresh_token'] ?? null;
        $identity->token_expires_at = now()->addSeconds((int) ($token['expires_in'] ?? 3600));
        $identity->save();

        return $identity;
    }

    protected static function fail(string $message, ?string $to = null)
    {
        \Flash::error($message);

        return redirect($to ?: Backend::url('auth/signin'));
    }
}
