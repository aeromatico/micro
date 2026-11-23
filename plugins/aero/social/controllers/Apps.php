<?php namespace Aero\Social\Controllers;

use Backend\Classes\Controller;
use BackendMenu;

/** Llaves de apps propias del tenant (opcional) para YouTube, X y TikTok. */
class Apps extends Controller
{
    use \Aero\Social\Classes\ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.social.use', 'aero.social.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Social', 'social', 'apps');
    }

    public function formBeforeCreate($model): void
    {
        if (!\Aero\Social\Classes\CurrentTenant::id()) {
            throw new \ApplicationException('Tu usuario no pertenece a ninguna cuenta.');
        }
    }
}
