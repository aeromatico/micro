<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\UsageRecord;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

/**
 * UsageRecordTest — Validates the metric enum is enforced and the happy
 * path persists correctly. Validates Requirement 9.7 of the master-ads spec.
 */
class UsageRecordTest extends PluginTestCase
{
    /**
     * Build a fresh backend user with a unique login/email for the FK rules.
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

    private function bootSubscription(): Subscription
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Usage Workspace',
            'slug'     => 'usage-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $plan = Plan::create([
            'code'               => 'usage-plan-' . uniqid(),
            'name'               => 'Usage Plan',
            'monthly_price'      => 0,
            'max_meta_accounts'  => 1,
            'max_analyses_month' => 10,
            'auto_apply_allowed' => false,
        ]);

        return Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => '2025-01-01',
            'period_end'   => '2025-01-31',
        ]);
    }

    public function testRequiresMetricInEnum(): void
    {
        $sub = $this->bootSubscription();

        $this->expectException(ValidationException::class);

        // `metric` must be one of analysis|sync|applied_action.
        UsageRecord::create([
            'subscription_id' => $sub->id,
            'metric'          => 'foo',
            'qty'             => 1,
            'recorded_at'     => now(),
        ]);
    }

    public function testRecordsArePersisted(): void
    {
        $sub = $this->bootSubscription();

        $record = UsageRecord::create([
            'subscription_id' => $sub->id,
            'metric'          => 'analysis',
            'qty'             => 1,
            'recorded_at'     => now(),
        ]);

        $this->assertNotNull($record->id);
        $this->assertSame(1, UsageRecord::where('subscription_id', $sub->id)->count());

        $reloaded = UsageRecord::find($record->id);
        $this->assertSame('analysis', $reloaded->metric);
        $this->assertSame(1, (int) $reloaded->qty);
    }
}
