<?php namespace Aero\Realestate\Controllers;

use Aero\Realestate\Classes\CurrentTenant;
use Aero\Realestate\Classes\AgreementService;
use Aero\Realestate\Classes\RealestateException;
use Aero\Realestate\Classes\ScopesToTenant;
use Backend\Classes\Controller;
use BackendMenu;

class Documents extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.realestate.use', 'aero.realestate.agent', 'aero.realestate.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Realestate', 'realestate', 'documents');
    }

    public function formExtendModel($model)
    {
        if (!$model->exists) {
            foreach (['property_id', 'owner_id', 'client_id', 'agreement_id'] as $k) {
                get($k) && $model->{$k} = (int) get($k);
            }
        }

        return $model;
    }

    protected function requireAdmin(): void
    {
        if (!(CurrentTenant::isAdmin() || \BackendAuth::getUser()?->hasAccess('aero.realestate.use'))) {
            throw new \ApplicationException('Solo la administración de la inmobiliaria verifica documentos.');
        }
    }

    protected function setStatus(string $status)
    {
        $this->requireAdmin();
        $doc = \Aero\Realestate\Models\Document::visible()->findOrFail((int) post('id'));
        $doc->status = $status;
        $doc->save();
        \Flash::success('Documento actualizado.');

        return \Redirect::refresh();
    }

    public function update_onVerify() { return $this->setStatus('verified'); }
    public function update_onReject() { return $this->setStatus('rejected'); }

    /** Descarga del archivo privado: pasa por el panel, con permiso y filtrando por inmobiliaria; nunca hay URL pública. */
    public function download($id = null)
    {
        $doc = \Aero\Realestate\Models\Document::visible()->findOrFail((int) $id);
        $file = $doc->file;
        if (!$file) {
            throw new \ApplicationException('El documento no tiene archivo.');
        }

        $response = response()->download($file->getLocalPath(), $file->file_name, [
            'Content-Type' => $file->content_type ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
