<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\Exporter;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\Patient;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** Exportación completa: del paciente (personal clínico o propietario) y del negocio (propietario). Los datos son del negocio. */
class Exports extends Controller
{
    use ScopesToTenant;

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'exports');
    }

    public function index(): void
    {
        $this->pageTitle = 'Exportar datos';
        $this->vars['canBusiness'] = BackendAuth::getUser()->hasAccess(['aero.medical.settings', 'aero.medical.superadmin'], false);
    }

    public function patient($id = null)
    {
        $user = BackendAuth::getUser();
        if (!$user->hasAccess(['aero.medical.professional', 'aero.medical.settings', 'aero.medical.superadmin'], false)) {
            throw new \ApplicationException('Sin permiso para exportar.');
        }

        $p = $this->scopeToTenant(Patient::withTrashed()->whereKey((int) $id))->firstOrFail();

        return $this->send(fn () => Exporter::patient($p), $p->tenant_id, $p->id, 'paciente-' . $p->id . '.zip');
    }

    public function business()
    {
        if (!BackendAuth::getUser()->hasAccess(['aero.medical.settings', 'aero.medical.superadmin'], false)) {
            throw new \ApplicationException('Solo el propietario puede exportar todo el negocio.');
        }
        $tenantId = (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        return $this->send(fn () => Exporter::business($tenantId), $tenantId, null, 'historia-clinica-negocio-' . now()->format('Ymd') . '.zip');
    }

    protected function send(callable $build, $tenantId, $patientId, string $name)
    {
        try {
            $path = $build();
        } catch (MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        Audit::record('export', $tenantId, $patientId, $patientId ? 'patient' : 'business', $patientId);

        return response()->download($path, $name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }
}
