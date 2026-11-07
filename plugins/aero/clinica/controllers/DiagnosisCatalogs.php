<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class DiagnosisCatalogs extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.clinica.professional', 'aero.clinica.settings', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'diagnosiscatalogs');
    }

    /** Lista inicial de medicina general (solo códigos CIE-10 existentes; borrador sin revisión médica). */
    public function index_onLoadStarter()
    {
        $tenantId = (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        try {
            $r = \Aero\Clinica\Classes\StarterDiagnoses::load($tenantId);
        } catch (\Aero\Clinica\Classes\ClinicaException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success("Lista inicial: {$r['created']} agregados, {$r['skipped']} ya estaban" . ($r['missing'] ? ', ' . count($r['missing']) . ' códigos no están en el catálogo (' . implode(', ', $r['missing']) . ')' : '') . '. Es una selección sin revisión médica: ajústela.');

        return \Backend::redirect('aero/clinica/diagnosiscatalogs');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
