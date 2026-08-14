<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Properties;

use Aero\MasterAds\Models\AiAnalysis;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use BackendAuth;
use PluginTestCase;

/**
 * Property P2 — Aislamiento multi-tenant / Tenant Isolation.
 *
 * Validates: Property P2 / Requirements 1.3, 1.4.
 *
 * Formal statement (from design.md):
 *
 *     property_tenant_isolation: ∀ W1, W2 ∈ Workspace with W1 ≠ W2,
 *         ∀ user u where membership(u) = {W1}:
 *             let Q = query<TenantScoped>(authenticated as u) in
 *             ∀ row r ∈ Q:  r.workspace_id = W1.id
 *
 * Operationally: for every pair of distinct workspaces (W1, W2), a
 * {@see BackendUser} who is a member of W1 only must observe — when
 * querying tenant-scoped models — *exactly* the rows whose
 * `workspace_id = W1.id`. Rows belonging to W2 MUST never leak into the
 * result set, no matter how many of them exist.
 *
 * The property is exercised across the four direct-tenant-scoped models
 * that carry a `workspace_id` column and use the
 * {@see \Aero\MasterAds\Classes\Concerns\BelongsToTenantScope} trait:
 *
 *   - {@see MetaAccount}
 *   - {@see AiProvider}
 *   - {@see Subscription}
 *   - {@see AiAnalysis}
 *
 * For {@see MetaAccount} we sweep a randomised set of resource counts
 * `(w1Resources, w2Resources)` via {@see self::tenantConfigProvider()} so a
 * faulty scope that, for example, off-by-ones a `whereIn` clause is forced
 * to manifest across many cardinalities. The other three models use a
 * single canonical fixture per test because their leakage modes are
 * captured by the same global scope and additional cardinality coverage
 * is redundant.
 *
 * The final test method asserts the *complementary* invariant required
 * by the trait contract: when no Backend_User is authenticated (CLI or
 * queue-worker context) the scope is a no-op so background jobs can
 * legitimately read every tenant's data — proving the isolation is a
 * function of the authenticated subject, not of the model itself.
 */
class TenantIsolationTest extends PluginTestCase
{
    /**
     * Process-local monotonic counter — keeps slugs, backend-user logins
     * and Meta act ids unique across the data-provider iterations driven
     * into the same test method (and across test methods within one
     * process).
     */
    private static int $sequence = 0;

    /**
     * Yield 20 randomised `(w1Resources, w2Resources)` cardinality pairs
     * in [0, 5] × [0, 5] so the property is checked across the full
     * neighbourhood of "few rows" leakage modes: empty result sets,
     * single-row sets, and small multi-row sets — including the
     * degenerate case where W1 has no resources and any leak from W2
     * would immediately exceed the asserted bound.
     *
     * Bounded to 5 per workspace so each iteration stays well under a
     * second; the property's value lies in being exercised across many
     * distinct cardinality pairs, not in a single huge fixture.
     *
     * @return iterable<string, array{0: int, 1: int}>
     */
    public static function tenantConfigProvider(): iterable
    {
        foreach (range(1, 20) as $i) {
            yield "config-{$i}" => [mt_rand(0, 5), mt_rand(0, 5)];
        }
    }

    /**
     * Property P2 over {@see MetaAccount}, swept across the cardinality
     * grid from {@see self::tenantConfigProvider()}.
     *
     * Given two fresh, distinct workspaces W1 and W2, each seeded with
     * `$w1Resources` and `$w2Resources` MetaAccounts respectively,
     * authenticating as W1's owner-member and issuing
     * `MetaAccount::all()` MUST return exactly `$w1Resources` rows, all
     * carrying `workspace_id = W1.id`. A single row tagged with
     * `workspace_id = W2.id` would be an immediate counter-example.
     *
     * @dataProvider tenantConfigProvider
     */
    public function testTenantIsolationOnMetaAccount(int $w1Resources, int $w2Resources): void
    {
        [$user1, $w1] = $this->makeWorkspaceWithUser();
        [, $w2]       = $this->makeWorkspaceWithUser();

        $this->seedMetaAccounts($w1, $w1Resources);
        $this->seedMetaAccounts($w2, $w2Resources);

        BackendAuth::login($user1);

        $visible = MetaAccount::all();

        $this->assertSame(
            $w1Resources,
            $visible->count(),
            sprintf(
                'User of W1 must see exactly %d MetaAccount(s); '
                . 'observed %d (likely W2 leak — W2 had %d).',
                $w1Resources,
                $visible->count(),
                $w2Resources
            )
        );

        foreach ($visible as $account) {
            $this->assertSame(
                (int) $w1->id,
                (int) $account->workspace_id,
                'Found leaked MetaAccount from W2 when authenticated as W1 user'
            );
        }

        BackendAuth::logout();
    }

    /**
     * Property P2 over {@see AiProvider}.
     *
     * Each workspace owns exactly one AiProvider with a workspace-unique
     * API key sentinel. Authenticating as W1's owner-member MUST yield
     * the single row whose `workspace_id = W1.id` and never the row
     * belonging to W2 — including via the decrypted `api_key` accessor,
     * which we cross-check below to make absolutely sure the leak
     * detection does not depend on a single column.
     */
    public function testTenantIsolationOnAiProvider(): void
    {
        [$user1, $w1] = $this->makeWorkspaceWithUser();
        [, $w2]       = $this->makeWorkspaceWithUser();

        $this->makeAiProvider($w1, 'sk-w1');
        $this->makeAiProvider($w2, 'sk-w2');

        BackendAuth::login($user1);

        $visible = AiProvider::all();

        $this->assertSame(1, $visible->count(), 'W1 user must observe exactly one AiProvider');
        $this->assertSame((int) $w1->id, (int) $visible->first()->workspace_id);
        $this->assertSame(
            'sk-w1',
            (string) $visible->first()->api_key,
            'Decrypted api_key witness confirms the visible row belongs to W1'
        );

        BackendAuth::logout();
    }

    /**
     * Property P2 over {@see Subscription}.
     *
     * Each workspace gets one active subscription on the same plan; we
     * keep period bounds identical so the *only* attribute differing
     * between the two rows is `workspace_id`. Authenticating as W1's
     * owner-member MUST therefore yield W1's subscription and never
     * W2's, isolating the tenant-scope effect from any other filter.
     */
    public function testTenantIsolationOnSubscription(): void
    {
        [$user1, $w1] = $this->makeWorkspaceWithUser();
        [, $w2]       = $this->makeWorkspaceWithUser();
        $plan = $this->makePlan();

        Subscription::create([
            'workspace_id' => $w1->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => '2025-01-01',
            'period_end'   => '2025-01-31',
        ]);
        Subscription::create([
            'workspace_id' => $w2->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => '2025-01-01',
            'period_end'   => '2025-01-31',
        ]);

        BackendAuth::login($user1);

        $visible = Subscription::all();

        $this->assertSame(1, $visible->count(), 'W1 user must observe exactly one Subscription');
        $this->assertSame((int) $w1->id, (int) $visible->first()->workspace_id);

        BackendAuth::logout();
    }

    /**
     * Property P2 over {@see AiAnalysis}.
     *
     * Both workspaces run a successful analysis tied to their own
     * AiProvider; authenticating as W1's owner-member MUST yield W1's
     * analysis only. This case is interesting because AiAnalysis is the
     * pivot through which Recommendations / AppliedActions are
     * indirectly scoped — leaking it would compromise the entire
     * downstream audit trail.
     */
    public function testTenantIsolationOnAiAnalysis(): void
    {
        [$user1, $w1] = $this->makeWorkspaceWithUser();
        [, $w2]       = $this->makeWorkspaceWithUser();
        $provider1 = $this->makeAiProvider($w1, 'sk-w1');
        $provider2 = $this->makeAiProvider($w2, 'sk-w2');

        AiAnalysis::create([
            'workspace_id'   => $w1->id,
            'ai_provider_id' => $provider1->id,
            'target_type'    => 'campaign',
            'target_id'      => 1,
            'status'         => 'success',
        ]);
        AiAnalysis::create([
            'workspace_id'   => $w2->id,
            'ai_provider_id' => $provider2->id,
            'target_type'    => 'campaign',
            'target_id'      => 2,
            'status'         => 'success',
        ]);

        BackendAuth::login($user1);

        $visible = AiAnalysis::all();

        $this->assertSame(1, $visible->count(), 'W1 user must observe exactly one AiAnalysis');
        $this->assertSame((int) $w1->id, (int) $visible->first()->workspace_id);

        BackendAuth::logout();
    }

    /**
     * Complementary invariant — when NO Backend_User is authenticated,
     * the global scope is a deliberate no-op (see
     * {@see \Aero\MasterAds\Classes\Concerns\BelongsToTenantScope::bootBelongsToTenantScope()}).
     * This is what lets queue workers and `php artisan` commands operate
     * across every tenant.
     *
     * Seed W1 with 2 MetaAccounts and W2 with 3, then ensure no user is
     * logged in: `MetaAccount::all()` MUST return all 5 rows. A scope
     * that incorrectly returned an empty set in CLI context would break
     * `masterads:sync-all`, `masterads:rotate-tokens`, and every
     * scheduled job in the plugin.
     */
    public function testCliContextNoFiltering(): void
    {
        [, $w1] = $this->makeWorkspaceWithUser();
        [, $w2] = $this->makeWorkspaceWithUser();

        $this->seedMetaAccounts($w1, 2);
        $this->seedMetaAccounts($w2, 3);

        // No BackendAuth::login() — simulates CLI / queue worker context.
        BackendAuth::logout();

        $visible = MetaAccount::all();

        $this->assertSame(
            5,
            $visible->count(),
            'CLI/queue context (no authenticated user) must see all rows '
            . '(tenant scope must be a no-op).'
        );
    }

    // ────────────────────────────────────────────────────────────────
    //  Fixture helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Build a fresh, fully-valid {@see Workspace} together with a brand
     * new {@see BackendUser} that is both its `owner_id` and its
     * `members` pivot row (role = 'owner'). Both relations are
     * populated so the {@see \Aero\MasterAds\Classes\Concerns\BelongsToTenantScope}
     * trait's two-pronged lookup (owner_id ∪ pivot) finds the workspace
     * via either path — guarding against a regression that drops one of
     * the two predicates.
     *
     * The slug is `alpha_dash`-safe: `uniqid('', true)` returns a hex
     * string optionally containing a single dot when `more_entropy = true`,
     * so we replace any dot with a dash to satisfy the validator on
     * {@see Workspace::$rules}.
     *
     * @return array{0: BackendUser, 1: Workspace}
     */
    private function makeWorkspaceWithUser(): array
    {
        $token = str_replace('.', '-', uniqid('', true));
        $seq   = self::nextSequence();

        $user = BackendUser::create([
            'login'                 => 'iso_' . $seq . '_' . $token,
            'email'                 => 'iso_' . $seq . '_' . $token . '@example.test',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name'            => 'Iso',
            'last_name'             => 'Tester',
            'is_activated'          => true,
        ]);

        $workspace = Workspace::create([
            'name'     => 'Iso WS ' . $seq,
            'slug'     => 'iso-ws-' . $seq . '-' . $token,
            'owner_id' => $user->id,
        ]);

        // Attach the user as a workspace member with the 'owner' pivot role
        // so the second leg of `BelongsToTenantScope::workspaceIdsFor()`
        // also resolves to this workspace.
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        return [$user, $workspace];
    }

    /**
     * Bulk-create `$count` {@see MetaAccount} rows attached to the given
     * Workspace. Each row gets a per-iteration unique `meta_act_id` to
     * satisfy the `(workspace_id, meta_act_id)` UNIQUE index plus a
     * non-empty access token so the encryption mutator does not short-
     * circuit on NULL.
     *
     * `$count` may be zero — that is the explicit "empty W1" corner the
     * data provider covers.
     */
    private function seedMetaAccounts(Workspace $ws, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $seq = self::nextSequence();
            MetaAccount::create([
                'workspace_id' => $ws->id,
                'meta_act_id'  => 'act_' . (1_000_000 + $seq),
                'currency'     => 'USD',
                'access_token' => 'tok-' . $seq,
            ]);
        }
    }

    /**
     * Create a single non-default {@see AiProvider} for the given
     * Workspace, with a caller-supplied plaintext `api_key` that doubles
     * as a per-workspace sentinel — letting individual assertions verify
     * the visible row belongs to the expected tenant via the decrypted
     * accessor.
     *
     * `is_default = false` is intentional: the `afterSave` invariant that
     * demotes the previous default fires only on `true`, and we want
     * each call to be a self-contained insert with no cross-tenant side
     * effect.
     */
    private function makeAiProvider(Workspace $ws, string $apiKey): AiProvider
    {
        $seq = self::nextSequence();

        return AiProvider::create([
            'workspace_id' => $ws->id,
            'name'         => 'Provider ' . $seq,
            'driver'       => 'openrouter',
            'model'        => 'anthropic/claude-3.5-sonnet',
            'api_key'      => $apiKey,
            'is_default'   => false,
        ]);
    }

    /**
     * Create a minimal {@see Plan} usable as the `plan_id` FK for any
     * Subscription this test inserts. The exact caps are irrelevant for
     * P2 (which is about query scoping, not quota enforcement) but the
     * Plan validation rules require all fields to be present.
     */
    private function makePlan(): Plan
    {
        $seq = self::nextSequence();

        return Plan::create([
            'code'               => 'iso-plan-' . $seq,
            'name'               => 'Iso Plan ' . $seq,
            'monthly_price'      => 0,
            'max_meta_accounts'  => 5,
            'max_analyses_month' => 10,
            'auto_apply_allowed' => false,
        ]);
    }

    /**
     * Return the next value of the process-local monotonic counter; used
     * by every helper to mint unique values for columns that carry a
     * UNIQUE index (login, email, slug, meta_act_id, plan code).
     */
    private static function nextSequence(): int
    {
        return ++self::$sequence;
    }
}
