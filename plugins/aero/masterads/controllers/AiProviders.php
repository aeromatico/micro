<?php namespace Aero\MasterAds\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * AiProviders — Backend CRUD controller for LLM provider credentials.
 *
 * Surfaces the {@see \Aero\MasterAds\Models\AiProvider} list and form so
 * tenant admins can register the LLM endpoints used by the analysis engine
 * (`driver` = openrouter | openai | anthropic | custom), pick the `model`
 * string, store the `api_key`, mark one entry as `is_default` per workspace
 * and persist driver-specific `settings` (JSON). The `api_key` field is
 * rendered as `type: password` (see `models/aiprovider/fields.yaml`) and
 * the AiProvider model encrypts it on write — the value is never echoed
 * back to the form (Requirement 5.5, 17.6).
 *
 * Behaviors:
 *  - FormController — create/update/preview screens driven by
 *                     `config_form.yaml` and `models/aiprovider/fields.yaml`.
 *  - ListController — index list driven by `config_list.yaml` and
 *                     `models/aiprovider/columns.yaml`.
 *
 * Navigation context: side-menu entry `aiproviders` under the `masterads`
 * top-level menu, registered in `Plugin::registerNavigation()` and guarded
 * by the same `aero.masterads.manage_ai_providers` permission used here.
 *
 * Authorization: gated by `aero.masterads.manage_ai_providers` —
 * registered in `Plugin::registerPermissions()`. Tenant isolation is
 * enforced at the model layer via the `BelongsToTenantScope` global scope
 * on AiProvider (Requirement 10.x), so a user with the permission still
 * only sees the providers attached to workspaces they belong to.
 *
 * Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 12.2, 17.2, 20.1
 */
class AiProviders extends Controller
{
    /**
     * Behaviors implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * Form behavior configuration file (relative to this controller's
     * `aiproviders/` view directory).
     */
    public $formConfig = 'config_form.yaml';

    /**
     * List behavior configuration file.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * Permission required to access any action on this controller.
     * Mirrors the side-menu permission so unauthorized users get a 403
     * both from the menu link and from a direct URL hit.
     */
    public $requiredPermissions = ['aero.masterads.manage_ai_providers'];

    /**
     * Bind the active backend menu so the sidebar highlights
     * "Proveedores IA" when any action of this controller is rendered.
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.MasterAds', 'masterads', 'aiproviders');
    }
}
