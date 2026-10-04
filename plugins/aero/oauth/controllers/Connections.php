<?php namespace Aero\Oauth\Controllers;

use Aero\Oauth\Classes\Oauth;
use Aero\Oauth\Classes\Providers\ProviderRegistry;
use Aero\Oauth\Classes\ScopeRegistry;
use Aero\Oauth\Models\Identity;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;
use Redirect;

/** Cuentas conectadas del usuario actual (y, para el superadmin, de todos). */
class Connections extends Controller
{
    public $requiredPermissions = ['aero.oauth.use', 'aero.oauth.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Oauth', 'oauth');
        $this->pageTitle = 'Cuentas conectadas';
    }

    public function index(): void
    {
        $user = BackendAuth::getUser();
        $this->vars['providers'] = ProviderRegistry::all();
        $this->vars['mine'] = Identity::where('backend_user_id', $user->id)->get()->keyBy('provider');
        $this->vars['scopes'] = collect(ProviderRegistry::all())->map(fn ($p) => ScopeRegistry::all($p->code()));
        $this->vars['everyone'] = $user->hasAccess('aero.oauth.superadmin')
            ? Identity::with('user')->orderByDesc('id')->limit(200)->get()
            : collect();
    }

    /** Conecta la cuenta (con los permisos extra que el usuario marque). */
    public function onConnect()
    {
        $provider = (string) post('provider');
        abort_unless(ProviderRegistry::get($provider), 404);

        return Redirect::to(Oauth::connectUrl($provider, (array) post('scopes', []), '/' . ltrim(parse_url(\Backend::url('aero/oauth/connections'), PHP_URL_PATH), '/')));
    }

    public function onDisconnect()
    {
        $user = BackendAuth::getUser();
        $identity = Identity::where('backend_user_id', $user->id)->where('provider', (string) post('provider'))->first();

        if ($identity) {
            try {
                $token = $identity->refresh_token ?: $identity->access_token;
                $token && ProviderRegistry::get($identity->provider)?->revoke($token);
            } catch (\Throwable) {
                // Si Google no responde igual se borra el vínculo local.
            }
            $identity->delete();
            Flash::success('Cuenta desconectada.');
        }

        return Redirect::refresh();
    }
}
