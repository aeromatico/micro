<?php namespace Aero\MasterAds\Models;

use Model;
use October\Rain\Database\Traits\Validation;
use October\Rain\Exception\ValidationException;

/**
 * Workspace — Multi-tenant root entity of the Master Ads plugin.
 *
 * Every business-domain record (MetaAccount, Campaign, AiAnalysis,
 * Subscription, …) belongs to exactly one Workspace, which enforces the
 * tenant-isolation contract (Property 2 / Requirements 1.3, 1.4).
 *
 * Membership and per-workspace roles (owner / admin / viewer) are kept on the
 * `aero_masterads_workspace_user` pivot table; the `owner_id` column on the
 * Workspace itself stores the original creator, granting them implicit full
 * privileges (Requirement 12.3).
 *
 * Cross-model relation targets are kept as fully-qualified class-name strings
 * (`MetaAccount::class`, `Subscription::class`) because those models are
 * created in subsequent tasks (4.2 / 4.4). `::class` is a compile-time
 * constant in PHP, so it does NOT trigger autoload merely by being mentioned
 * here — the relation is only resolved when the relation accessor is invoked.
 *
 * Validates: Requirements 1.1, 1.2, 1.5, 1.6, 17.1, 17.6
 */
class Workspace extends Model
{
    use Validation;

    /**
     * Underlying database table.
     */
    public $table = 'aero_masterads_workspaces';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = ['name', 'slug', 'owner_id', 'settings'];

    /**
     * Attributes cast to/from JSON when accessed.
     */
    protected $jsonable = ['settings'];

    /**
     * Validation rules — enforced by the Validation trait.
     *
     * - `name`     : max 120 chars, required (Requirement 1.1).
     * - `slug`     : URL-safe identifier, UNIQUE in table (Requirement 1.2).
     * - `owner_id` : FK to `backend_users` (Requirement 1.1).
     */
    public $rules = [
        'name'     => 'required|max:120',
        'slug'     => 'required|alpha_dash|unique:aero_masterads_workspaces,slug',
        'owner_id' => 'required|exists:backend_users,id',
    ];

    /**
     * Belongs-To relations.
     */
    public $belongsTo = [
        'owner' => [\Backend\Models\User::class, 'key' => 'owner_id'],
    ];

    /**
     * Has-Many relations. Targets are referenced by class-name string so this
     * file can be loaded before the children models exist on disk (tasks 4.2
     * and 4.4 create them).
     */
    public $hasMany = [
        'meta_accounts' => [\Aero\MasterAds\Models\MetaAccount::class],
        'subscriptions' => [\Aero\MasterAds\Models\Subscription::class],
    ];

    /**
     * Many-to-many membership with `Backend\Models\User`, carrying the
     * per-workspace `role` (owner | admin | viewer) on the pivot row.
     */
    public $belongsToMany = [
        'members' => [
            \Backend\Models\User::class,
            'table' => 'aero_masterads_workspace_user',
            'pivot' => ['role'],
        ],
    ];

    /**
     * Block deletion of a Workspace that still owns active billing or any
     * connected Meta accounts. Enforces Requirement 1.6 ("THE Master_Ads
     * SHALL impedir eliminar un Workspace que tenga Subscriptions activas o
     * Meta_Accounts conectados…").
     *
     * Throwing `ValidationException` lets the backend Form/ListController
     * surface the error as a flash message without leaking domain detail.
     *
     * @throws ValidationException when an active subscription or any meta
     *         account is still attached.
     */
    public function beforeDelete(): void
    {
        if ($this->subscriptions()->where('status', 'active')->exists()) {
            throw new ValidationException([
                'subscriptions' => 'No se puede eliminar un Workspace con suscripciones activas.',
            ]);
        }

        if ($this->meta_accounts()->exists()) {
            throw new ValidationException([
                'meta_accounts' => 'No se puede eliminar un Workspace con cuentas Meta conectadas.',
            ]);
        }
    }
}
