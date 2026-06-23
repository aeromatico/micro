<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Billing;

use Aero\MasterAds\Classes\Billing\PlanLimiter;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\UsageRecord;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use Carbon\Carbon;
use PluginTestCase;

/**
 * PlanLimiterTest — Exercises the read-only quota service over real DB
 * rows. Every test boots the minimal Workspace + Plan + Subscription
 * (+ UsageRecord) scaffolding the limiter inspects and then calls a
 * single public method on a freshly instantiated `PlanLimiter`.
 *
 * Validates: Requirements 9.3, 9.5, 9.7 of the master-ads spec.
 */
class PlanLimiterTest extends \PluginTestCase
{
    // ------------------------------------------------------------------
    // Fixture helpers — keep each test focused on its own assertions.
    // ------------------------------------------------------------------

    /**
     * Build a fresh backend user with a unique login/email so multiple
     * tests in the same suite cannot collide on the unique-index rules
     * `backend_users` exposes via the validation trait.
     */
    private function createBackendUser(): BackendUser
    {
        $token = uniqid('', true);
        return BackendUser::create([
            'login'                 => 'tester_' . $token,
            'email'                 => 'tester_' . $token . '@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name'            => 'Test',
            'last_name'             => 'User',
        ]);
    }

    /**
     * Create a Workspace owned by a brand-new backend user.
     */
    private function createWorkspace(): Workspace
    {
        $owner = $this->createBackendUser();

        return Workspace::create([
            'name'     => 'PL Workspace',
            'slug'     => 'pl-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);
    }

    /**
     * Create a Plan with the supplied caps. Defaults pick safe, generous
     * values so the test only has to override what it actually cares
     * about (e.g. `max_analyses_month` or `auto_apply_allowed`).
     *
     * @param  array<string,mixed> $overrides
     */
    private function createPlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'code'               => 'pl-plan-' . uniqid(),
            'name'               => 'PL Plan',
            'monthly_price'      => 0,
            'max_meta_accounts'  => 10,
            'max_analyses_month' => 10,
            'auto_apply_allowed' => false,
        ], $overrides));
    }

    /**
     * Create an `active` Subscription joining the Workspace and Plan over
     * the canonical Jan 2025 billing window. Callers can override status
     * or period bounds by passing them in `$overrides`.
     *
     * @param  array<string,mixed> $overrides
     */
    private function createSubscription(Workspace $ws, Plan $plan, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'workspace_id' => $ws->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => '2025-01-01',
            'period_end'   => '2025-01-31',
        ], $overrides));
    }

    /**
     * Insert `$count` UsageRecord rows against a Subscription, all with
     * the same metric and `recorded_at`. Used to seed the analysis (or
     * sync) tallies the limiter reads back.
     */
    private function seedUsage(Subscription $sub, string $metric, int $count, Carbon $recordedAt): void
    {
        for ($i = 0; $i < $count; $i++) {
            UsageRecord::create([
                'subscription_id' => $sub->id,
                'metric'          => $metric,
                'qty'             => 1,
                'recorded_at'     => $recordedAt,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // canRunAnalysis()
    // ------------------------------------------------------------------

    /**
     * Without any Subscription at all, the Workspace has no plan budget,
     * so `canRunAnalysis()` MUST return false (no rows to read, nothing
     * to allow).
     */
    public function testCanRunAnalysisReturnsFalseWithoutActiveSubscription(): void
    {
        $ws = $this->createWorkspace();

        $this->assertFalse((new PlanLimiter())->canRunAnalysis($ws));
    }

    /**
     * With an active Subscription whose Plan caps at 10 analyses and
     * zero UsageRecord rows already recorded, the limiter MUST allow a
     * new analysis (0 < 10).
     */
    public function testCanRunAnalysisReturnsTrueWhenUnderQuota(): void
    {
        $ws   = $this->createWorkspace();
        $plan = $this->createPlan(['max_analyses_month' => 10]);
        $this->createSubscription($ws, $plan);

        $this->assertTrue((new PlanLimiter())->canRunAnalysis($ws));
    }

    /**
     * With the cap set to 5 and exactly 5 `analysis` UsageRecord rows
     * already inside the billing window, the budget is fully consumed
     * and a new analysis MUST be rejected (5 < 5 is false).
     */
    public function testCanRunAnalysisReturnsFalseAtCap(): void
    {
        $ws   = $this->createWorkspace();
        $plan = $this->createPlan(['max_analyses_month' => 5]);
        $sub  = $this->createSubscription($ws, $plan);

        // Five analyses inside the Jan 2025 window: count == cap → blocked.
        $this->seedUsage($sub, 'analysis', 5, Carbon::parse('2025-01-15'));

        $this->assertFalse((new PlanLimiter())->canRunAnalysis($ws));
    }

    /**
     * Usage rows older than `period_start` belong to a previous billing
     * window. They MUST be ignored by the in-period tally, leaving the
     * current budget intact (5 rows outside → count == 0 → allowed).
     */
    public function testCanRunAnalysisIgnoresUsageOutsidePeriod(): void
    {
        $ws   = $this->createWorkspace();
        $plan = $this->createPlan(['max_analyses_month' => 5]);
        $sub  = $this->createSubscription($ws, $plan);

        // Pre-period usage: a fortnight before period_start, so the
        // limiter's BETWEEN clause excludes every one of these rows.
        $this->seedUsage($sub, 'analysis', 5, Carbon::parse('2024-12-15'));

        $this->assertTrue((new PlanLimiter())->canRunAnalysis($ws));
    }

    /**
     * The analysis budget is counted ONLY against `metric = 'analysis'`.
     * Five `sync` rows inside the window are irrelevant to that tally,
     * so a new analysis MUST still be permitted.
     */
    public function testCanRunAnalysisFiltersByMetricAnalysis(): void
    {
        $ws   = $this->createWorkspace();
        $plan = $this->createPlan(['max_analyses_month' => 5]);
        $sub  = $this->createSubscription($ws, $plan);

        // Five `sync` events in-window — must NOT count against analyses.
        $this->seedUsage($sub, 'sync', 5, Carbon::parse('2025-01-15'));

        $this->assertTrue((new PlanLimiter())->canRunAnalysis($ws));
    }

    // ------------------------------------------------------------------
    // canConnectMetaAccount()
    // ------------------------------------------------------------------

    /**
     * With `max_meta_accounts = 2` and two MetaAccount rows already
     * attached to the Workspace, the limiter MUST refuse to connect
     * another one (count == cap → blocked).
     */
    public function testCanConnectMetaAccountReturnsFalseAtCap(): void
    {
        $ws   = $this->createWorkspace();
        $plan = $this->createPlan(['max_meta_accounts' => 2]);
        $this->createSubscription($ws, $plan);

        // Two MetaAccount rows: matches the cap exactly.
        MetaAccount::create([
            'workspace_id' => $ws->id,
            'meta_act_id'  => 'act_111111',
            'currency'     => 'USD',
        ]);
        MetaAccount::create([
            'workspace_id' => $ws->id,
            'meta_act_id'  => 'act_222222',
            'currency'     => 'USD',
        ]);

        $this->assertFalse((new PlanLimiter())->canConnectMetaAccount($ws));
    }

    // ------------------------------------------------------------------
    // canAutoApply()
    // ------------------------------------------------------------------

    /**
     * `canAutoApply()` is a direct reflection of the active Plan's
     * `auto_apply_allowed` flag. Two Workspaces, two Plans (one with
     * the bit set, one without), one assertion each.
     */
    public function testCanAutoApplyReflectsPlan(): void
    {
        $limiter = new PlanLimiter();

        // Allowed plan → true.
        $wsAllowed   = $this->createWorkspace();
        $planAllowed = $this->createPlan(['auto_apply_allowed' => true]);
        $this->createSubscription($wsAllowed, $planAllowed);
        $this->assertTrue($limiter->canAutoApply($wsAllowed));

        // Blocked plan → false.
        $wsBlocked   = $this->createWorkspace();
        $planBlocked = $this->createPlan(['auto_apply_allowed' => false]);
        $this->createSubscription($wsBlocked, $planBlocked);
        $this->assertFalse($limiter->canAutoApply($wsBlocked));
    }

    // ------------------------------------------------------------------
    // activeSubscription()
    // ------------------------------------------------------------------

    /**
     * When more than one active Subscription exists for the same
     * Workspace (e.g. an overlapping renewal), `activeSubscription()`
     * MUST resolve to the one with the latest `period_end` — the
     * most-recently extended billing window.
     */
    public function testActiveSubscriptionReturnsMostRecentByPeriodEnd(): void
    {
        $ws   = $this->createWorkspace();
        $plan = $this->createPlan();

        // Earlier window.
        $earlier = $this->createSubscription($ws, $plan, [
            'period_start' => '2025-01-01',
            'period_end'   => '2025-01-31',
        ]);

        // Later window — must be the one returned.
        $later = $this->createSubscription($ws, $plan, [
            'period_start' => '2025-02-01',
            'period_end'   => '2025-02-28',
        ]);

        $active = (new PlanLimiter())->activeSubscription($ws);

        $this->assertNotNull($active);
        $this->assertSame($later->id, $active->id);
        $this->assertNotSame($earlier->id, $active->id);
    }

    // ------------------------------------------------------------------
    // remainingAnalyses()
    // ------------------------------------------------------------------

    /**
     * With a cap of 10 and 3 in-period analysis records, the remaining
     * budget MUST be `10 - 3 = 7`. Without any Subscription at all the
     * budget MUST be 0 (no plan, no budget).
     */
    public function testRemainingAnalyses(): void
    {
        $limiter = new PlanLimiter();

        // Scenario A: 3 analyses consumed, 7 still available.
        $wsActive = $this->createWorkspace();
        $plan     = $this->createPlan(['max_analyses_month' => 10]);
        $sub      = $this->createSubscription($wsActive, $plan);
        $this->seedUsage($sub, 'analysis', 3, Carbon::parse('2025-01-10'));

        $this->assertSame(7, $limiter->remainingAnalyses($wsActive));

        // Scenario B: Workspace without any Subscription → 0 remaining.
        $wsNoSub = $this->createWorkspace();
        $this->assertSame(0, $limiter->remainingAnalyses($wsNoSub));
    }
}
