<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

/**
 * SubscriptionTest — Validates that period ordering is enforced and that
 * the `scopeActive` query restricts results to billing-active statuses.
 * Validates Requirements 9.1, 9.2 of the master-ads spec.
 */
class SubscriptionTest extends PluginTestCase
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

    /**
     * Build the minimal Workspace + Plan scaffolding the subscription rows
     * need to satisfy their FK existence rules.
     */
    private function bootScaffolding(): array
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Sub Workspace',
            'slug'     => 'sub-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $plan = Plan::create([
            'code'               => 'sub-plan-' . uniqid(),
            'name'               => 'Sub Plan',
            'monthly_price'      => 0,
            'max_meta_accounts'  => 1,
            'max_analyses_month' => 10,
            'auto_apply_allowed' => false,
        ]);

        return [$workspace, $plan];
    }

    public function testPeriodEndMustBeAfterStart(): void
    {
        [$workspace, $plan] = $this->bootScaffolding();

        $this->expectException(ValidationException::class);

        // period_end == period_start violates the `after:period_start` rule.
        Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => '2025-01-01',
            'period_end'   => '2025-01-01',
        ]);
    }

    public function testScopeActiveFiltersByStatus(): void
    {
        [$workspace, $plan] = $this->bootScaffolding();

        $active = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => '2025-01-01',
            'period_end'   => '2025-01-31',
        ]);

        $trialing = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'trialing',
            'period_start' => '2025-02-01',
            'period_end'   => '2025-02-28',
        ]);

        $canceled = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'canceled',
            'period_start' => '2025-03-01',
            'period_end'   => '2025-03-31',
        ]);

        $activeIds = Subscription::active()->pluck('id')->all();

        $this->assertCount(2, $activeIds);
        $this->assertContains($active->id, $activeIds);
        $this->assertContains($trialing->id, $activeIds);
        $this->assertNotContains($canceled->id, $activeIds);
    }
}
