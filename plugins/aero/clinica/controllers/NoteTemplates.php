<?php namespace Aero\Clinica\Controllers;

use Aero\Clinica\Classes\CurrentTenant;
use Aero\Clinica\Classes\ScopesToTenant;
use Aero\Clinica\Models\NoteTemplate;
use Backend\Classes\Controller;
use BackendMenu;

class NoteTemplates extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.clinica.settings', 'aero.clinica.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Clinica', 'clinica', 'notetemplates');
    }

    public function index()
    {
        if ($id = CurrentTenant::id()) {
            NoteTemplate::ensureDefaults($id);
        }

        $this->asExtension('ListController')->index();
    }

    /** Agrega las plantillas sugeridas (control crónico y chequeo preventivo) que falten. */
    public function index_onAddSuggested()
    {
        $tenantId = (int) CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        try {
            $r = \Aero\Clinica\Classes\SuggestedTemplates::load($tenantId);
        } catch (\Aero\Clinica\Classes\ClinicaException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success("Plantillas sugeridas: {$r['created']} agregadas, {$r['skipped']} ya existían. Edítelas a su manera.");

        return \Backend::redirect('aero/clinica/notetemplates');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
