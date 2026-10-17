<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Classes\ScopesToTenant;
use Aero\Medical\Models\NoteTemplate;
use Backend\Classes\Controller;
use BackendMenu;

class NoteTemplates extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.medical.settings', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'notetemplates');
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
            $r = \Aero\Medical\Classes\SuggestedTemplates::load($tenantId);
        } catch (\Aero\Medical\Classes\MedicalException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success("Plantillas sugeridas: {$r['created']} agregadas, {$r['skipped']} ya existían. Edítelas a su manera.");

        return \Backend::redirect('aero/medical/notetemplates');
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }
    }
}
