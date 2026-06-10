<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use BackendMenu;
use Backend\Classes\Controller;

class ContactSubmissions extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\ListController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.sites.view_submissions'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'contactsubmissions');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }
}
