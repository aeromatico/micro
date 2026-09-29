<?php namespace Aero\Docs\Controllers;

use Aero\Docs\Models\Guide;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

/**
 * Revisión de las guías interactivas que genera docs-sync. Lo que llega de la
 * IA queda en `pending_html`; aquí se previsualiza y se aprueba o rechaza.
 */
class Guides extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.docs.manage'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Docs', 'docs', 'guides');
    }

    public function listExtendQuery($query): void
    {
        $query->inCurrentScope();
    }

    public function formExtendQuery($query): void
    {
        $query->inCurrentScope();
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = \Aero\Docs\Classes\DocsScope::currentTenantId();
        }
    }

    public function onApprove($id = null)
    {
        $this->assertReviewer();
        $guide = Guide::inCurrentScope()->findOrFail($id ?: post('record_id'));
        $guide->approve(BackendAuth::getUser()?->id);
        Flash::success('Guía publicada.');

        return \Backend::redirect('aero/docs/guides/update/' . $guide->id);
    }

    public function onReject($id = null)
    {
        $this->assertReviewer();
        $guide = Guide::inCurrentScope()->findOrFail($id ?: post('record_id'));
        $guide->reject(BackendAuth::getUser()?->id, post('reason') ?: null);
        Flash::success('Propuesta descartada. Lo publicado no cambió.');

        return \Backend::redirect('aero/docs/guides/update/' . $guide->id);
    }

    protected function assertReviewer(): void
    {
        if (!BackendAuth::getUser()?->hasAccess('aero.docs.guides.review')) {
            throw new \ApplicationException('No tienes permiso para aprobar guías.');
        }
    }
}
