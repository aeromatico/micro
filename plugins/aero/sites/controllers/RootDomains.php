<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\RootDomain;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;

class RootDomains extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $formConfig   = 'config_form.yaml';
    public $listConfig   = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'rootdomains');
    }

    public function onDelete(): mixed
    {
        $checkedIds = post('checked');

        if (!is_array($checkedIds) || empty($checkedIds)) {
            Flash::error('No se seleccionaron dominios raíz.');
            return $this->listRefresh();
        }

        $count = 0;
        $skipped = 0;
        foreach ($checkedIds as $id) {
            $domain = RootDomain::withCount('tenants')->find((int) $id);
            if (!$domain) {
                continue;
            }
            if ($domain->tenants_count > 0) {
                $skipped++;
                continue;
            }
            $domain->delete();
            $count++;
        }

        if ($count > 0) {
            Flash::success("{$count} dominio(s) raíz eliminado(s).");
        }
        if ($skipped > 0) {
            Flash::warning("{$skipped} dominio(s) no se eliminaron porque tienen tenants asociados.");
        }

        return $this->listRefresh();
    }
}
