<?php namespace Aero\Sites\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

class Plans extends Controller
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
        BackendMenu::setContext('Aero.Sites', 'sites', 'plans');
    }

    /** Pestaña «Límites»: un campo por límite que declaren los plugins. */
    public function formExtendFields($form): void
    {
        if ($form->isNested || $form->model !== null && !$form->model instanceof \Aero\Sites\Models\Plan) {
            return;
        }

        $fields = [];
        foreach (\Aero\Sites\Classes\PlanLimits::definitions() as $key => $def) {
            $fields["limits[{$key}]"] = [
                'tab'     => 'Límites',
                'label'   => $def['label'],
                'type'    => 'number',
                'min'     => 0,
                'span'    => 'auto',
                'comment' => ($def['comment'] ?? '') . ' Vacío = sin límite.',
            ];
        }

        if ($fields) {
            $form->addTabFields($fields);
        }
    }
}
