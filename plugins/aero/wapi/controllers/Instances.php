<?php namespace Aero\Wapi\Controllers;

use Aero\Hello\Models\Account;
use Aero\Hello\Models\Profile;
use Aero\Wapi\Classes\WapiClient;
use Aero\Wapi\Classes\WapiCredentials;
use Aero\Wapi\Classes\WapiInstances;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

/**
 * Flujo de conexión por QR de WhatsApp Web (wapi): crear instancia → escanear
 * → queda dada de alta como Account (driver=wapi) en Aero.Hello, lista para
 * que SendMessageJob/ProcessWebhookEventJob la usen igual que a Zernio.
 *
 * No usa FormController/ListController: el estado real vive en wapi (la
 * instancia) y en Aero\Hello\Models\Account (la cuenta), no en un modelo
 * propio de este plugin.
 */
class Instances extends Controller
{
    public $requiredPermissions = ['aero.hello.manage_accounts'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Wapi', 'wapi', 'instances');
    }

    public function index()
    {
        $this->pageTitle = 'wapi — Números conectados';
        $this->vars['accounts'] = $this->accounts();
        $this->vars['profiles'] = Profile::orderBy('label')->pluck('label', 'id');
        $this->vars['isConfigured'] = \Aero\Wapi\Models\Settings::isConfigured();
    }

    protected function accounts()
    {
        return Account::ofDriver('wapi')->orderByDesc('connected_at')->get();
    }

    /**
     * Resuelve el cliente de wapi para un perfil dado (null = credenciales
     * globales) — el mismo perfil se usa para crear la instancia, pollear su
     * estado, registrar su webhook y, más tarde, enviar/recibir mensajes por
     * esa cuenta (WapiChannelDriver::clientFor() hace la misma resolución).
     */
    protected function instancesFor(?int $profileId): WapiInstances
    {
        $profile = $profileId ? Profile::find($profileId) : null;

        return new WapiInstances(new WapiClient(WapiCredentials::apiKey($profile)));
    }

    // -------------------------------------------------------------------
    // AJAX — flujo de conexión
    // -------------------------------------------------------------------

    public function onStartConnect()
    {
        $label = trim((string) post('label'));
        $profileId = post('profile_id') ?: null;

        if ($label === '') {
            throw new \ApplicationException('Ponle un nombre a la conexión (p.ej. el número o el negocio).');
        }

        $method = post('method') === 'code' ? 'code' : 'qr';
        $pairingPhone = WapiInstances::pairingPhoneFromInput($method, post('phone'));

        $instances = $this->instancesFor($profileId ? (int) $profileId : null);
        $instance = $instances->create($label);
        $instances->connect($instance['id'], $pairingPhone);

        return [
            '#wapi-connect-area' => $this->makePartial('connect_qr', [
                'instanceId' => $instance['id'],
                'label'      => $label,
                'profileId'  => $profileId,
                'method'     => $method,
                'phone'      => $pairingPhone,
            ]),
        ];
    }

    /**
     * Llamado desde JS cada pocos segundos mientras el modal de conexión
     * está abierto. Cuando wapi reporta `connected`, da de alta la Account
     * y registra el webhook en el mismo paso — no hay un tercer click.
     */
    public function onPollInstance()
    {
        $instanceId = post('instance_id');
        $label      = post('label');
        $profileId  = post('profile_id') ?: null;
        $method     = post('method') === 'code' ? 'code' : 'qr';
        $instances  = $this->instancesFor($profileId ? (int) $profileId : null);
        $status     = $instances->status($instanceId);

        if (($status['status'] ?? null) === 'connected') {
            $account = $this->finalizeConnection($instanceId, $label, $profileId, $status, $instances);

            return [
                'connected'           => true,
                '#wapi-connect-area'  => $this->makePartial('connect_done', ['account' => $account]),
                '#wapi-account-list'  => $this->makePartial('list', ['accounts' => $this->accounts()]),
            ];
        }

        return [
            'connected' => false,
            'status'    => $status['status'] ?? 'unknown',
            'qr'        => $method === 'qr' ? $instances->qr($instanceId) : null,
            'code'      => $method === 'code' ? $instances->pairingCode($instanceId) : null,
        ];
    }

    protected function finalizeConnection(
        string $instanceId,
        string $label,
        ?string $profileId,
        array $status,
        WapiInstances $instances
    ): Account {
        $account = Account::updateOrCreate(
            ['zernio_account_id' => $instanceId],
            [
                'driver'       => 'wapi',
                'profile_id'   => $profileId ?: null,
                'platform'     => 'whatsapp',
                'label'        => $label,
                'phone_number' => $status['phoneNumber'] ?? null,
                'status'       => 'connected',
                'is_enabled'   => true,
                'connected_at' => now(),
            ]
        );

        $instances->registerMessageWebhook($instanceId);

        return $account;
    }

    // -------------------------------------------------------------------
    // AJAX — gestión
    // -------------------------------------------------------------------

    /**
     * "Cancelar" durante el escaneo: borra la instancia recién creada en
     * wapi (todavía no tiene Account local, así que no hay nada que borrar
     * de este lado) y vuelve al formulario inicial.
     */
    public function onCancelConnect()
    {
        $instanceId = post('instance_id');
        $profileId  = post('profile_id') ?: null;

        if ($instanceId) {
            try {
                $this->instancesFor($profileId ? (int) $profileId : null)->delete($instanceId);
            } catch (\Throwable $e) {
                // Best-effort: si ya se conectó o wapi tardó en responder,
                // no vale la pena bloquear al usuario por esto.
            }
        }

        return ['#wapi-connect-area' => $this->makePartial('connect_form', [
            'profiles' => Profile::orderBy('label')->pluck('label', 'id'),
        ])];
    }

    public function onDeleteAccount()
    {
        $account = Account::ofDriver('wapi')->findOrFail((int) post('id'));

        try {
            $this->instancesFor($account->profile_id)->delete($account->zernio_account_id);
        } catch (\Throwable $e) {
            // La instancia ya pudo haber sido borrada del lado de wapi
            // (dashboard propio, límite de plan, etc.) - no bloquear el
            // desvincular local por eso.
        }

        $account->delete();

        Flash::success('Número desconectado.');

        return ['#wapi-account-list' => $this->makePartial('list', ['accounts' => $this->accounts()])];
    }
}
