<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Ranges extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'ranges');
    }

    /** Carga el borrador de referencia para adultos: solo lo que falta, solo modo humano, nunca pisa lo del negocio. */
    public function index_onLoadReference()
    {
        $tenantId = (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        try {
            $r = \Aero\Medical\Classes\ReferenceRanges::load($tenantId);
        } catch (\Aero\Medical\Classes\MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Aero\Medical\Classes\Audit::record('update', $tenantId, null, 'ranges', null, ['reference_loaded']);
        \Flash::success("Rangos de referencia: {$r['created']} agregados, {$r['skipped']} ya existían. Son un borrador: pida a un médico que los revise.");

        return \Backend::redirect('aero/medical/ranges');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
