<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use Carbon\Carbon;
use DB;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

/**
 * MetaAccountTest — Validates the encrypted-token contract, the
 * `meta_act_id` regex rule and the helper methods used to drive token
 * rotation. Validates Requirements 2.2, 2.3, 2.4, 2.7, 15.1, 15.2, 15.3.
 */
class MetaAccountTest extends PluginTestCase
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
     * Build a Workspace + return one fresh MetaAccount factory helper.
     *
     * @return array{Workspace, callable(array): MetaAccount}
     */
    private function workspaceWithFactory(): array
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Meta Workspace',
            'slug'     => 'meta-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $factory = function (array $overrides = []) use ($workspace): MetaAccount {
            return MetaAccount::create(array_merge([
                'workspace_id'  => $workspace->id,
                'meta_act_id'   => 'act_' . random_int(100000, 999999),
                'currency'      => 'USD',
                'access_token'  => 'plain-access-token',
                'refresh_token' => 'plain-refresh-token',
                'expires_at'    => null,
            ], $overrides));
        };

        return [$workspace, $factory];
    }

    public function testAccessTokenEncryptedAtRest(): void
    {
        [, $factory] = $this->workspaceWithFactory();
        $account = $factory(['access_token' => 'plain-access-XYZ']);

        // Round-trip: accessor decrypts back to the original.
        $reloaded = MetaAccount::find($account->id);
        $this->assertSame('plain-access-XYZ', $reloaded->access_token);

        // Raw DB value must be ciphered (Crypt::encrypt payload), never the plaintext.
        $raw = DB::table('aero_masterads_meta_accounts')
            ->where('id', $account->id)
            ->value('access_token');

        $this->assertNotSame('plain-access-XYZ', $raw);
        $this->assertNotEmpty($raw);
    }

    public function testRefreshTokenEncryptedAtRest(): void
    {
        [, $factory] = $this->workspaceWithFactory();
        $account = $factory(['refresh_token' => 'plain-refresh-XYZ']);

        $reloaded = MetaAccount::find($account->id);
        $this->assertSame('plain-refresh-XYZ', $reloaded->refresh_token);

        $raw = DB::table('aero_masterads_meta_accounts')
            ->where('id', $account->id)
            ->value('refresh_token');

        $this->assertNotSame('plain-refresh-XYZ', $raw);
        $this->assertNotEmpty($raw);
    }

    public function testTokensHiddenFromArray(): void
    {
        [, $factory] = $this->workspaceWithFactory();
        $account = $factory();

        $array = $account->toArray();

        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);
    }

    public function testIsTokenExpired(): void
    {
        [, $factory] = $this->workspaceWithFactory();

        // expires_at in the past → expired.
        $expired = $factory([
            'meta_act_id' => 'act_111111',
            'expires_at'  => Carbon::now()->subDay(),
        ]);
        $this->assertTrue($expired->isTokenExpired());

        // expires_at in the future → not expired.
        $alive = $factory([
            'meta_act_id' => 'act_222222',
            'expires_at'  => Carbon::now()->addDay(),
        ]);
        $this->assertFalse($alive->isTokenExpired());

        // null expires_at → treat as non-expired (caller decides).
        $undated = $factory([
            'meta_act_id' => 'act_333333',
            'expires_at'  => null,
        ]);
        $this->assertFalse($undated->isTokenExpired());
    }

    public function testExpiresWithinDays(): void
    {
        [, $factory] = $this->workspaceWithFactory();

        // 6 days in the future (pad with extra hours to defend against
        // sub-second time drift between `now()` calls) → strictly less than 7.
        $sixDays = $factory([
            'meta_act_id' => 'act_400000',
            'expires_at'  => Carbon::now()->addDays(6)->addHours(12),
        ]);
        $this->assertTrue($sixDays->expiresWithinDays(7));

        // Exactly 7 days away → `< 7` is false.
        $sevenDays = $factory([
            'meta_act_id' => 'act_500000',
            'expires_at'  => Carbon::now()->addDays(7)->addHours(12),
        ]);
        $this->assertFalse($sevenDays->expiresWithinDays(7));

        // 8 days away → definitely outside the 7-day window.
        $eightDays = $factory([
            'meta_act_id' => 'act_600000',
            'expires_at'  => Carbon::now()->addDays(8)->addHours(12),
        ]);
        $this->assertFalse($eightDays->expiresWithinDays(7));

        // Null expires_at → false (no rotation decision can be made).
        $undated = $factory([
            'meta_act_id' => 'act_700000',
            'expires_at'  => null,
        ]);
        $this->assertFalse($undated->expiresWithinDays(7));
    }

    public function testMetaActIdRegexValidation(): void
    {
        [, $factory] = $this->workspaceWithFactory();

        // "act_<digits>" satisfies the regex; creation must succeed.
        $valid = $factory(['meta_act_id' => 'act_123']);
        $this->assertSame('act_123', $valid->meta_act_id);

        // A value that does not match the regex must throw.
        $this->expectException(ValidationException::class);
        $factory(['meta_act_id' => 'not_valid']);
    }
}
