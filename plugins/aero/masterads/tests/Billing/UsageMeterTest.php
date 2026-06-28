<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Billing;

use Aero\MasterAds\Classes\Billing\UsageMeter;
use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\UsageRecord;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use Carbon\Carbon;
use InvalidArgumentException;
use PluginTestCase;

/**
 * UsageMeterTest — Validates the append-only contract of `UsageMeter::record()`:
 * - Happy path persists a row and returns the populated model.
 * - Domain rules ("metric must be whitelisted", "qty must be >= 1") are
 *   checked BEFORE the DB is touched and surface as InvalidArgumentException.
 * - `recorded_at` is stamped with the current wall-clock time.
 *
 * Validates: Requirement 9.7 of the master-ads spec.
 */
class UsageMeterTest extends \PluginTestCase
{
    // ------------------------------------------------------------------
    // Fixture helpers
    // ------------------------------------------------------------------

    /**
     * Build a fresh backend user with a unique login/email so multiple
     * tests can run side-by-side without colliding on unique indexes.
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
     * Boot the minimal Workspace + Plan + Subscription chain `UsageMeter`
     * needs to point its UsageRecord rows at. The Subscription is left
     * `active` over a roomy window so `recorded_at = now()` falls inside.
     */
    private function bootSubscription(): Subscription
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Usage Workspace',
            'slug'     => 'um-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $plan = Plan::create([
            'code'               => 'um-plan-' . uniqid(),
            'name'               => 'UM Plan',
            'monthly_price'      => 0,
            'max_meta_accounts'  => 1,
            'max_analyses_month' => 10,
            'auto_apply_allowed' => false,
        ]);

        // A wide period centred on today so a `now()`-stamped record will
        // always land inside (test stays stable across calendar days).
        return Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => Carbon::now()->subYear()->toDateString(),
            'period_end'   => Carbon::now()->addYear()->toDateString(),
        ]);
    }

    // ------------------------------------------------------------------
    // record() — happy path
    // ------------------------------------------------------------------

    /**
     * The canonical successful call: a valid metric and the default
     * qty=1 MUST produce a persisted UsageRecord row whose attributes
     * match the inputs. The returned model must already carry an id
     * (i.e. it really was saved, not just instantiated).
     */
    public function testRecordCreatesUsageRecord(): void
    {
        $sub    = $this->bootSubscription();
        $meter  = new UsageMeter();
        $record = $meter->record($sub, 'analysis');

        // Returned model is populated.
        $this->assertInstanceOf(UsageRecord::class, $record);
        $this->assertNotNull($record->id);
        $this->assertSame($sub->id, (int) $record->subscription_id);
        $this->assertSame('analysis', $record->metric);
        $this->assertSame(1, (int) $record->qty);
        $this->assertNotNull($record->recorded_at);

        // Row is persisted — round-trip via the DB to prove it.
        $reloaded = UsageRecord::find($record->id);
        $this->assertNotNull($reloaded);
        $this->assertSame('analysis', $reloaded->metric);
        $this->assertSame(1, (int) $reloaded->qty);
        $this->assertSame($sub->id, (int) $reloaded->subscription_id);
    }

    // ------------------------------------------------------------------
    // record() — invariant violations
    // ------------------------------------------------------------------

    /**
     * A metric outside the {analysis, sync, applied_action} whitelist
     * MUST raise InvalidArgumentException BEFORE any row is created.
     */
    public function testRecordRejectsInvalidMetric(): void
    {
        $sub   = $this->bootSubscription();
        $meter = new UsageMeter();

        $this->expectException(InvalidArgumentException::class);

        $meter->record($sub, 'foo');
    }

    /**
     * Non-positive quantities (qty=0 and qty=-1) MUST both be rejected
     * with InvalidArgumentException — the meter does not record "zero"
     * or "negative" events.
     */
    public function testRecordRejectsNonPositiveQty(): void
    {
        $sub   = $this->bootSubscription();
        $meter = new UsageMeter();

        // qty = 0 → must throw.
        $threwOnZero = false;
        try {
            $meter->record($sub, 'analysis', 0);
        } catch (InvalidArgumentException $e) {
            $threwOnZero = true;
        }
        $this->assertTrue($threwOnZero, 'UsageMeter::record() must reject qty=0.');

        // qty = -1 → must also throw.
        $threwOnNegative = false;
        try {
            $meter->record($sub, 'analysis', -1);
        } catch (InvalidArgumentException $e) {
            $threwOnNegative = true;
        }
        $this->assertTrue($threwOnNegative, 'UsageMeter::record() must reject qty=-1.');

        // Sanity: neither call should have left a row behind.
        $this->assertSame(0, UsageRecord::where('subscription_id', $sub->id)->count());
    }

    // ------------------------------------------------------------------
    // record() — timestamping
    // ------------------------------------------------------------------

    /**
     * `recorded_at` MUST be stamped with the current wall-clock time
     * (within a small drift tolerance to account for test scheduler
     * jitter and DB datetime truncation). Five seconds is the budget.
     */
    public function testRecordUsesCurrentTimestamp(): void
    {
        $sub    = $this->bootSubscription();
        $before = Carbon::now();
        $record = (new UsageMeter())->record($sub, 'analysis');
        $after  = Carbon::now();

        $this->assertNotNull($record->recorded_at);

        $recordedAt = Carbon::parse($record->recorded_at);

        // The timestamp lies between the moment the test took a "before"
        // snapshot and the moment it took the "after" snapshot, modulo
        // the 5-second tolerance the spec allows.
        $this->assertLessThanOrEqual(
            5,
            abs($recordedAt->diffInSeconds(Carbon::now())),
            'recorded_at must be within 5 seconds of now().'
        );

        // Belt-and-braces: it sits between the explicit before/after
        // boundaries (with the same 5s slack) so the value is not, say,
        // years in the past or the future.
        $this->assertGreaterThanOrEqual(
            $before->copy()->subSeconds(5)->getTimestamp(),
            $recordedAt->getTimestamp()
        );
        $this->assertLessThanOrEqual(
            $after->copy()->addSeconds(5)->getTimestamp(),
            $recordedAt->getTimestamp()
        );
    }
}
