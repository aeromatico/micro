<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class DocumentTemplates extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.clinica.professional', 'aero.clinica.settings', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'documenttemplates');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }

    /** Agrega los formatos médicos de partida que faltan; nunca pisa los del negocio. */
    public function index_onLoadDefaults()
    {
        $tenantId = (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        $r = \Aero\Clinica\Classes\DefaultDocuments::load($tenantId);
        \Aero\Clinica\Classes\Audit::record('update', $tenantId, null, 'document_templates', null, ['defaults_loaded']);
        \Flash::success("Formatos: {$r['created']} agregados, {$r['skipped']} ya existían. Revíselos antes de usarlos.");

        return $this->listRefresh();
    }
}
