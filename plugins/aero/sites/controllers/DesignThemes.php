<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\DesignTheme;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;

class DesignThemes extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'designthemes');
    }

    public function onDelete(): mixed
    {
        $checkedIds = post('checked');

        if (!is_array($checkedIds) || empty($checkedIds)) {
            Flash::error('No se seleccionaron temas.');
            return $this->listRefresh();
        }

        $count = DesignTheme::whereIn('id', $checkedIds)->delete();

        if ($count > 0) {
            Flash::success("{$count} tema(s) eliminado(s).");
        }

        return $this->listRefresh();
    }
}
