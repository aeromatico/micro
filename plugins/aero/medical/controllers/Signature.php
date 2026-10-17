<?php namespace Aero\Medical\Controllers;

use Aero\Medical\Classes\CurrentTenant;
use Aero\Medical\Models\Professional;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

/** Cada profesional edita SOLO su propio perfil de firma. */
class Signature extends Controller
{
    public $implement = [\Backend\Behaviors\FormController::class];

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.medical.professional', 'aero.medical.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Medical', 'medical', 'signature');
    }

    public function index()
    {
        $tenantId = CurrentTenant::id();
        if (!$tenantId) {
            throw new \ApplicationException('No se pudo determinar su negocio.');
        }

        $row = Professional::forUser($tenantId, BackendAuth::getUser());

        return \Backend::redirect('aero/medical/signature/update/' . $row->id);
    }

    public function update_onUploadImage($recordId = null)
    {
        $file = \Request::file(post('which') . '_file');
        if (!$file || !$file->isValid()) {
            throw new \ApplicationException('No se recibió la imagen.');
        }

        $this->mine($recordId)->storeImage((string) post('which'), $file);
        \Flash::success('Imagen guardada.');

        return \Backend::redirect('aero/medical/signature/update/' . (int) $recordId);
    }

    public function update_onRemoveImage($recordId = null)
    {
        $this->mine($recordId)->removeImage((string) post('which'));
        \Flash::success('Imagen quitada.');

        return \Backend::redirect('aero/medical/signature/update/' . (int) $recordId);
    }

    protected function mine($recordId): Professional
    {
        $q = Professional::whereKey((int) $recordId);
        $this->formExtendQuery($q);

        return $q->firstOrFail();
    }

    /** Falla cerrado: solo el perfil del propio usuario en su negocio. */
    public function formExtendQuery($query): void
    {
        $query->where('tenant_id', (int) CurrentTenant::id())->where('user_id', (int) BackendAuth::getUser()?->id);
    }
}
