<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Subscriptions — Backend CRUD controller for workspace billing subscriptions.
 *
 * Surfaces the per-workspace `Subscription` records that bind a `Workspace`
 * to a `Plan` and track lifecycle (`trialing`, `active`, `past_due`,
 * `canceled`) plus the current billing period. The list supports filtering
 * by status and by workspace through `config_filter.yaml`.
 *
 * Behaviors:
 *  - FormController — create/update/preview screens, driven by
 *                     `config_form.yaml` and `models/subscription/fields.yaml`.
 *  - ListController — index list with filters, driven by
 *                     `config_list.yaml`, `config_filter.yaml`, and
 *                     `models/subscription/columns.yaml`.
 *
 * Permissions:
 *  - All actions are gated by `aero.masterads.manage_billing`
 *    (Requirement 12.2). Tenant scoping of the list (workspaces the
 *    operator can see) is enforced by the `TenantScope` applied on the
 *    `Subscription` Eloquent model.
 *
 * Validates: Requirements 9.1, 9.2, 12.2, 17.2, 20.1
 */
class Subscriptions extends Controller
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
     * `subscriptions/` view directory).
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
     * Bind the active backend menu so the sidebar highlights "Subscriptions".
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'subscriptions');
    }
}
