<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\Campaign;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

/**
 * CampaignTest — Validates the idempotent `upsertByMetaId` static (the
 * structural underpinning of Property P4 / sync idempotency) and that the
 * status enum is enforced. Validates Requirements 3.2, 3.3, 4.2.
 */
class CampaignTest extends PluginTestCase
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

    private function bootMetaAccount(): MetaAccount
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Campaign Workspace',
            'slug'     => 'campaign-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        return MetaAccount::create([
            'workspace_id' => $workspace->id,
            'meta_act_id'  => 'act_999000',
            'currency'     => 'USD',
            'access_token' => 'token',
        ]);
    }

    public function testUpsertByMetaIdIsIdempotent(): void
    {
        $metaAccount = $this->bootMetaAccount();

        // Meta delivers budgets as integer-string minor units (cents).
        $payload = [
            'id'           => 'cmp_1',
            'name'         => 'A',
            'status'       => 'ACTIVE',
            'daily_budget' => 1000,
        ];

        // Call twice with identical payload — must be a no-op on the
        // second run (Property P4: sync idempotency).
        Campaign::upsertByMetaId($payload, $metaAccount->id);
        Campaign::upsertByMetaId($payload, $metaAccount->id);

        $this->assertSame(1, Campaign::count());

        $campaign = Campaign::where('meta_id', 'cmp_1')->first();
        $this->assertNotNull($campaign);
        $this->assertSame('A', $campaign->name);
        // 1000 cents in minor units → 10.00 in the workspace currency.
        $this->assertSame(10.00, (float) $campaign->daily_budget);
    }

    public function testStatusEnumValidation(): void
    {
        $metaAccount = $this->bootMetaAccount();

        $this->expectException(ValidationException::class);

        // "INVALID" is not part of the in:ACTIVE,PAUSED,ARCHIVED,DELETED rule.
        Campaign::create([
            'meta_account_id' => $metaAccount->id,
            'meta_id'         => 'cmp_invalid',
            'name'            => 'Invalid Status Campaign',
            'status'          => 'INVALID',
        ]);
    }
}
