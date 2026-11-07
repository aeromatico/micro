<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Ranges extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.clinica.professional', 'aero.clinica.settings', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'ranges');
    }

    /** Carga el borrador de referencia para adultos: solo lo que falta, nunca pisa lo del negocio. */
    public function index_onLoadReference()
    {
        $tenantId = (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        try {
            $r = \Aero\Clinica\Classes\ReferenceRanges::load($tenantId);
        } catch (\Aero\Clinica\Classes\ClinicaException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Aero\Clinica\Classes\Audit::record('update', $tenantId, null, 'ranges', null, ['reference_loaded']);
        \Flash::success("Rangos de referencia: {$r['created']} agregados, {$r['skipped']} ya existían. Son un borrador: pida a un médico que los revise.");

        return \Backend::redirect('aero/clinica/ranges');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
