<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Plans — Backend CRUD controller for billing plans.
 *
 * Surfaces the global Plan catalog (e.g. starter / pro / enterprise) used
 * by `Subscription` to gate per-workspace quotas and capabilities. The
 * Plan list, form and preview screens are reachable from the side-menu
 * entry `plans` registered in `Plugin::registerNavigation()`.
 *
 * Behaviors:
 *  - FormController — create/update/preview screens, driven by
 *                     `config_form.yaml` and `models/plan/fields.yaml`.
 *  - ListController — index list, driven by `config_list.yaml` and
 *                     `models/plan/columns.yaml`.
 *
 * Permissions:
 *  - All actions are gated by `aero.masterads.manage_billing`
 *    (Requirement 12.2). Plans are tenant-agnostic global records, so no
 *    workspace scoping is applied here.
 *
 * Validates: Requirements 9.1, 9.2, 12.2, 17.2, 20.1
 */
class Plans extends Controller
{
    /**
     * Behaviors implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * Form behavior configuration file (relative to the controller's
     * `plans/` view directory).
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List behavior configuration file.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Permission required to access any action on this controller.
     */
    public $requiredPermissions = ['aero.masterads.manage_billing'];

    /**
     * Bind the active backend menu so the sidebar highlights "Plans".
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'plans');
    }
}
