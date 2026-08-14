<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Concerns;

/**
 * BelongsToTenantScope — global query scope that confines a model's rows to
 * the Workspaces the current Backend_User belongs to.
 *
 * Applied to models that carry a direct `workspace_id` column (MetaAccount,
 * Subscription, AiAnalysis, AiProvider). Models without a direct
 * `workspace_id` — Campaign, AdSet, Ad, Recommendation, AppliedAction — are
 * scoped indirectly through their parent relation and MUST NOT use this trait.
 *
 * Behavior:
 *   - When `BackendAuth::getUser()` returns null (CLI, queue worker without
 *     a logged-in user), the scope is a no-op so background jobs and console
 *     commands can read every tenant's data.
 *   - When the current user has the system-wide permission
 *     `aero.masterads.manage_workspaces`, the scope is also a no-op
 *     (super-admin bypass) — this powers backend administration UIs that
 *     legitimately need cross-tenant visibility.
 *   - Otherwise the query is restricted to rows whose `workspace_id` is in
 *     the set of Workspaces the user owns OR is a member of (rows in
 *     `aero_masterads_workspace_user`).
 *
 * The list of accessible Workspace IDs is resolved once per request and
 * memoized in a static cache keyed by user id to avoid hammering the DB on
 * every query.
 *
 * Validates: Requirements 1.3, 1.4 (master-ads spec).
 */
trait BelongsToTenantScope
{
    /**
     * Boot the trait — registers the `tenant` global scope on the model.
     *
     * October's Eloquent variant auto-invokes any `bootXxx` static method
     * named after a trait used by the model, so simply using this trait is
     * enough to attach the scope.
     */
    public static function bootBelongsToTenantScope(): void
    {
        static::addGlobalScope('tenant', function ($query) {
            $user = \BackendAuth::getUser();

            // No authenticated Backend_User → background context (queue
            // worker, console command). Do not filter — the calling code is
            // expected to scope explicitly when needed.
            if (!$user) {
                return;
            }

            // Super-admin bypass: users entrusted with managing workspaces
            // need cross-tenant visibility to administer the system.
            if ($user->hasAccess('aero.masterads.manage_workspaces')) {
                return;
            }

            $ids = self::workspaceIdsFor($user);
            $table = $query->getModel()->getTable();

            // Fully-qualify the column so the predicate survives JOINs that
            // would otherwise introduce ambiguous column references.
            $query->whereIn($table . '.workspace_id', $ids);
        });
    }

    /**
     * Resolve the set of Workspace IDs accessible to a Backend_User.
     *
     * Combines workspaces the user owns (`aero_masterads_workspaces.owner_id`)
     * with workspaces where the user is a member (rows in the pivot
     * `aero_masterads_workspace_user`). Result is cached per-request in a
     * static array keyed by user id.
     *
     * @param  object $user  The authenticated Backend_User instance.
     * @return array<int>    De-duplicated list of accessible Workspace IDs.
     */
    protected static function workspaceIdsFor($user): array
    {
        static $cache = [];

        $key = (int) $user->id;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $owned = \DB::table('aero_masterads_workspaces')
            ->where('owner_id', $user->id)
            ->pluck('id')
            ->all();

        $member = \DB::table('aero_masterads_workspace_user')
            ->where('user_id', $user->id)
            ->pluck('workspace_id')
            ->all();

        return $cache[$key] = array_values(array_unique(array_merge($owned, $member)));
    }
}
