<?php namespace Aero\Livechat\Controllers;

use Aero\Livechat\Classes\TenantScope;
use Aero\Livechat\Models\ChannelSettings as Settings;
use Backend;
use Backend\Classes\Controller;
use BackendMenu;

/**
 * Configuración de Livechat por tenant (plataforma si no hay tenant): cuenta
 * de Hello y switch del puente a WhatsApp. Un solo registro por ámbito, así
 * que el index redirige directo a su formulario.
 */
class ChannelSettings extends Controller
{
    public $implement = [\Backend\Behaviors\FormController::class];

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.livechat.manage_settings'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Livechat', 'livechat', 'livechat-settings');
    }

    public function index()
    {
        return Backend::redirect('aero/livechat/channelsettings/update/' . Settings::forScope(TenantScope::currentTenantId())->id);
    }

    /** Falla cerrado: solo el registro del propio ámbito. */
    public function formExtendQuery($query): void
    {
        $tenantId = TenantScope::currentTenantId();
        $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');
    }
}
