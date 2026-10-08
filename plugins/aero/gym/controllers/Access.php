<?php namespace Aero\Gym\Controllers;

use Aero\Gym\Classes\AccessControl;
use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\GymQr;
use Aero\Gym\Models\AccessLog;
use Aero\Gym\Models\GymSettings;
use Backend\Classes\Controller;
use BackendMenu;

/** Puesto de control: lector de QR/carnet (teclado) o digitación manual. */
class Access extends Controller
{
    public $requiredPermissions = ['aero.gym.use', 'aero.gym.superadmin'];

    public $pageTitle = 'Control de acceso';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Gym', 'gym', 'access');
    }

    public $gymQrMode = false;

    public function index(): void
    {
        $tenantId = CurrentTenant::id();
        $this->gymQrMode = $tenantId && GymSettings::accessMode($tenantId) === 'gym_qr';
    }

    /** Modo gym_qr: la URL vigente del QR (cambia cada N segundos) y los últimos ingresos. */
    public function onQr(): array
    {
        $tenantId = CurrentTenant::id();
        if (!$tenantId || GymSettings::accessMode($tenantId) !== 'gym_qr') {
            throw new \ApplicationException('Este gimnasio no usa el QR del gimnasio. Cambia el modo en Configuración.');
        }

        $qr = app(GymQr::class);
        $ttl = $qr->ttl($tenantId);

        return [
            'url'     => $qr->url($tenantId),
            'expires' => $ttl - (time() % $ttl),
            'recent'  => AccessLog::with('member')->where('tenant_id', $tenantId)->orderByDesc('id')->limit(6)->get()
                ->map(fn ($l) => [
                    'name' => $l->member?->name ?: 'Desconocido', 'granted' => (bool) $l->granted,
                    'reason' => $l->reason, 'at' => $l->created_at->format('H:i:s'),
                ])->all(),
        ];
    }

    public function onCheck()
    {
        $tenantIds = CurrentTenant::accessibleIds();
        if (!$tenantIds) {
            throw new \ApplicationException('No se pudo determinar su gimnasio.');
        }

        $code = trim((string) post('credential'));
        if ($code === '') {
            throw new \ApplicationException('Escanee o escriba la credencial.');
        }

        $result = app(AccessControl::class)->checkAny($tenantIds, $code, post('method') === 'card' ? 'card' : 'qr');

        return ['#gym-access-result' => $this->makePartial('result', ['r' => $result])];
    }
}
