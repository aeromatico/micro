<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Models\Archetype;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;

class Archetypes extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $formConfig    = 'config_form.yaml';
    public $listConfig    = 'config_list.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'archetypes');
    }

    public function onDelete(): mixed
    {
        $checkedIds = post('checked');

        if (!is_array($checkedIds) || empty($checkedIds)) {
            Flash::error('No se seleccionaron arquetipos.');
            return $this->listRefresh();
        }

        $count = Archetype::whereIn('id', $checkedIds)->delete();

        if ($count > 0) {
            Flash::success("{$count} arquetipo(s) eliminado(s).");
        }

        return $this->listRefresh();
    }
}
