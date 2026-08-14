<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Workspaces — Backend CRUD controller for the tenant-root entity.
 *
 * Surfaces the Workspace list, form and member-management UI to backend
 * users. All actions are gated by the `aero.masterads.manage_workspaces`
 * permission registered in `Plugin::registerPermissions()`.
 *
 * Behaviors:
 *  - FormController     — create/update/preview screens, driven by
 *                         `config_form.yaml` and `models/workspace/fields.yaml`.
 *  - ListController     — index list, driven by `config_list.yaml` and
 *                         `models/workspace/columns.yaml`.
 *  - RelationController — exposes the `members` belongsToMany relation with
 *                         the per-workspace `role` pivot column.
 *
 * Navigation context: registered under the `Aero.MasterAds` plugin with the
 * top-level `masterads` menu and the `workspaces` side-menu entry (see
 * `Plugin::registerNavigation()`).
 *
 * Validates: Requirements 1.1, 1.5, 1.6, 12.2, 17.2, 20.1
 */
class Workspaces extends Controller
{
    /**
     * Behaviors implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    /**
     * Form behavior configuration file (relative to this controller's
     * `workspaces/` view directory).
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List behavior configuration file.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Relation behavior configuration file — defines the `members` pivot UI.
     */
    public $relationConfig = 'config_relation.yaml';

    /**
     * Permission required to access any action on this controller.
     * Owners (Workspace.owner_id) still bypass per Requirement 12.3, but
     * that authorization is enforced at the model/policy layer, not here.
     */
    public $requiredPermissions = ['aero.masterads.manage_workspaces'];

    /**
     * Bind the active backend menu so the sidebar highlights "Workspaces"
     * when any action of this controller is rendered.
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'workspaces');
    }
}
