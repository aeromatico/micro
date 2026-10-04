<?php namespace Aero\Docs\Controllers;

use Aero\Docs\Classes\DocsScope;
use Aero\Docs\Models\TenantSetting;
use Backend\Classes\Controller;
use BackendMenu;
use ApplicationException;
use Flash;

/** Interruptor de la documentación del tenant actual. Desactivado por defecto. */
class DocsSettings extends Controller
{
    public $requiredPermissions = ['aero.docs.manage'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Docs', 'docs', 'settings');
    }

    public function index()
    {
        $this->pageTitle = 'Configuración de documentación';
        $this->vars['enabled'] = TenantSetting::enabledFor(DocsScope::currentTenantId());
        $this->vars['isPlatform'] = DocsScope::currentTenantId() === null;
    }

    public function onSave()
    {
        $tenantId = DocsScope::currentTenantId();
        if (!$tenantId) {
            throw new ApplicationException('La documentación de la plataforma está siempre disponible; el interruptor es de cada tenant.');
        }

        TenantSetting::updateOrCreate(['tenant_id' => $tenantId], ['enabled' => (bool) post('enabled')]);
        Flash::success(post('enabled') ? 'Documentación activada en tu sitio.' : 'Documentación desactivada en tu sitio.');

        return \Backend::redirect('aero/docs/docssettings');
    }
}
