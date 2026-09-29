<?php namespace Aero\Docs\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

class Articles extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.docs.manage'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Docs', 'docs', 'articles');
    }

    public function listExtendQuery($query): void
    {
        $query->inCurrentScope();
    }

    /** Sin esto se podría abrir por URL un registro de otro sitio. */
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

    public function onApprovePending()
    {
        $this->assertReviewer();
        $article = \Aero\Docs\Models\Article::inCurrentScope()->findOrFail(post('record_id'));
        $article->approvePending();
        \Flash::success('Cambios aprobados. El artículo ya muestra el contenido nuevo.');

        return \Backend::redirect('aero/docs/articles/update/' . $article->id);
    }

    public function onRejectPending()
    {
        $this->assertReviewer();
        $article = \Aero\Docs\Models\Article::inCurrentScope()->findOrFail(post('record_id'));
        $article->rejectPending();
        \Flash::success('Cambios descartados. El artículo publicado no cambió.');

        return \Backend::redirect('aero/docs/articles/update/' . $article->id);
    }

    protected function assertReviewer(): void
    {
        if (!\BackendAuth::getUser()?->hasAccess('aero.docs.guides.review')) {
            throw new \ApplicationException('No tienes permiso para aprobar cambios.');
        }
    }
}
