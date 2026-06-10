<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\SeoConfig;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Redirect;

class SeoConfigs extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
    ];

    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sites.manage_seo'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'seoconfigs');
    }

    public function index()
    {
        $tenantId = $this->getCurrentTenantId();
        $record = SeoConfig::where('tenant_id', $tenantId)->first();

        if ($record) {
            return Redirect::to(Backend::url("aero/sites/seoconfigs/update/{$record->id}"));
        }

        return Redirect::to(Backend::url('aero/sites/seoconfigs/create'));
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
        }
    }
}
