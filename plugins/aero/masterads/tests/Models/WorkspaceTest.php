<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Plan;
use Aero\MasterAds\Models\Subscription;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

/**
 * WorkspaceTest — Validation rules and beforeDelete guards for the tenant
 * root entity. Validates Requirements 1.1, 1.2, 1.5, 1.6 of the master-ads
 * spec.
 */
class WorkspaceTest extends PluginTestCase
{
    /**
     * Build a freshly-minted backend user with a unique login / email so
     * Workspace owner_id FK rules are satisfied without colliding with
     * previously-created rows.
     */
    private function createBackendUser(array $overrides = []): BackendUser
    {
        $token = uniqid('', true);

        return BackendUser::create(array_merge([
            'login'                 => 'tester_' . $token,
            'email'                 => 'tester_' . $token . '@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'first_name'            => 'Test',
            'last_name'             => 'User',
        ], $overrides));
    }

    public function testRequiresNameAndSlug(): void
    {
        // `name`, `slug`, `owner_id` are all `required` — an empty payload
        // must fail validation before reaching the database.
        $this->expectException(ValidationException::class);

        Workspace::create([]);
    }

    public function testSlugMustBeUnique(): void
    {
        $owner = $this->createBackendUser();

        Workspace::create([
            'name'     => 'Alpha',
            'slug'     => 'alpha',
            'owner_id' => $owner->id,
        ]);

        $this->expectException(ValidationException::class);

        Workspace::create([
            'name'     => 'Alpha Duplicate',
            'slug'     => 'alpha',
            'owner_id' => $owner->id,
        ]);
    }

    public function testBeforeDeleteBlocksWhenActiveSubscriptionExists(): void
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Workspace with Sub',
            'slug'     => 'ws-with-sub',
            'owner_id' => $owner->id,
        ]);

        $plan = Plan::create([
            'code'               => 'plan-basic',
            'name'               => 'Basic',
            'monthly_price'      => 9.99,
            'max_meta_accounts'  => 1,
            'max_analyses_month' => 10,
            'auto_apply_allowed' => false,
        ]);

        Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id'      => $plan->id,
            'status'       => 'active',
            'period_start' => now()->subDays(1)->toDateString(),
            'period_end'   => now()->addDays(29)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        $workspace->delete();
    }

    public function testBeforeDeleteBlocksWhenMetaAccountExists(): void
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Workspace with Meta',
            'slug'     => 'ws-with-meta',
            'owner_id' => $owner->id,
        ]);

        MetaAccount::create([
            'workspace_id' => $workspace->id,
            'meta_act_id'  => 'act_111111',
            'currency'     => 'USD',
            'access_token' => 'token-abc',
        ]);

        $this->expectException(ValidationException::class);

        $workspace->delete();
    }
}
