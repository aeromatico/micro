<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\CurrentTenant;
use Aero\Realestate\Classes\AgreementService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Templates extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'templates');
    }

    protected function tpl(): \Aero\Realestate\Models\AgreementTemplate
    {
        $query = \Aero\Realestate\Models\AgreementTemplate::query();
        $this->listExtendQuery($query);

        return $query->findOrFail((int) post('id'));
    }

    /** La lista incluye los modelos base (solo lectura) además de las plantillas propias. */
    public function listExtendQuery($query): void
    {
        if (!CurrentTenant::isAdmin()) {
            $id = CurrentTenant::id();
            $query->where(fn ($q) => $q->whereNull('tenant_id')->when($id, fn ($w) => $w->orWhere('tenant_id', $id)));
        }
    }

    /** Abrir un modelo base se permite (para leerlo y duplicarlo); guardarlo solo el superadmin (lo impone el modelo). */
    public function formExtendQuery($query): void
    {
        $this->listExtendQuery($query);
    }

    public function formExtendFields($form)
    {
        if ($form->model->exists && $form->model->tenant_id === null && !CurrentTenant::isAdmin()) {
            foreach ($form->getFields() as $field) {
                $field->readOnly = true;
            }
        }
    }

    public function update_onReviewForm() { return $this->makePartial('popup_review', ['m' => $this->tpl()]); }

    public function update_onReview()
    {
        $t = $this->tpl();
        if ($t->tenant_id === null && !CurrentTenant::isAdmin()) {
            throw new \ApplicationException('Los modelos base los revisa la plataforma: duplícalo para revisar tu copia.');
        }
        try {
            $t->markReviewed((string) post('by'), post('notes'));
        } catch (RealestateException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success('Revisión legal registrada.');

        return \Redirect::refresh();
    }

    public function update_onDuplicate()
    {
        $tpl = $this->tpl();
        $copy = $tpl->duplicate(CurrentTenant::isAdmin() ? $tpl->tenant_id : CurrentTenant::id());
        \Flash::success('Copia creada: ahora puedes adaptarla.');

        return \Redirect::to(\Backend::url('aero/realestate/templates/update/' . $copy->id));
    }
}
