<?php namespace Aero\Social\Controllers;

use Aero\Social\Classes\CurrentTenant;
use Aero\Social\Classes\MetaClient;
use Aero\Social\Classes\MetaConnect;
use Aero\Social\Classes\Networks;
use Aero\Social\Classes\SocialException;
use Aero\Social\Models\Account;
use Backend;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/** Tarjetas por red con su botón «Conectar». Cada tenant ve solo sus cuentas. */
class Connections extends Controller
{
    public $requiredPermissions = ['aero.social.use', 'aero.social.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Social', 'social', 'connections');
    }

    public function index()
    {
        $this->pageTitle = 'Conexiones';
        $tenantId = CurrentTenant::id();

        $this->vars['tenantId'] = $tenantId;
        $this->vars['networks'] = Networks::ALL;
        $this->vars['metaReady'] = MetaClient::isConfigured();
        $this->vars['accounts'] = Account::forTenant($tenantId)->where('status', '!=', 'disabled')
            ->orderBy('network')->orderBy('name')->get()->groupBy('network');
    }

    /** Botón «Conectar con Meta». */
    public function connect($provider = null)
    {
        $tenantId = CurrentTenant::id();

        if ($provider !== 'meta') {
            return Backend::redirect('aero/social/connections');
        }
        if (!$tenantId) {
            Flash::error('Tu usuario no pertenece a ninguna cuenta: no puede conectar redes.');

            return Backend::redirect('aero/social/connections');
        }
        if (!MetaClient::isConfigured()) {
            Flash::error('La conexión con Meta aún no está habilitada en la plataforma.');

            return Backend::redirect('aero/social/connections');
        }

        return redirect()->away(MetaConnect::authorizeUrl($tenantId, BackendAuth::getUser()->id));
    }

    /** Retorno del diálogo de Meta. */
    public function callback()
    {
        $tenantId = CurrentTenant::id();

        try {
            if (!$tenantId) {
                throw new SocialException('Tu usuario no pertenece a ninguna cuenta.');
            }

            $counts = MetaConnect::callback(request()->query(), $tenantId, BackendAuth::getUser()->id);

            if (!$counts['facebook']) {
                Flash::warning('Meta no devolvió ninguna página. Al conectar, marca las páginas (y sus Instagram) que quieres usar.');
            } else {
                Flash::success("Conectado: {$counts['facebook']} página(s) de Facebook y {$counts['instagram']} cuenta(s) de Instagram.");
            }
        } catch (SocialException $e) {
            Flash::error($e->getMessage());
        }

        return Backend::redirect('aero/social/connections');
    }

    public function index_onDisconnect()
    {
        $account = Account::forTenant(CurrentTenant::id())->findOrFail((int) post('id'));

        // Desconectar una página también apaga su Instagram (usa el mismo token).
        Account::forTenant(CurrentTenant::id())
            ->where(fn ($q) => $q->where('id', $account->id)->orWhere('parent_id', $account->id))
            ->get()
            ->each(fn ($a) => $a->update(['status' => 'disabled', 'access_token' => null]));

        Flash::success('Cuenta desconectada. Lo ya publicado no se borra de la red.');

        return Backend::redirect('aero/social/connections');
    }
}
