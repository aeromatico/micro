<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use DB;
use PluginTestCase;

/**
 * AiProviderTest — Asserts that the LLM provider configuration row
 *   - encrypts the `api_key` at rest,
 *   - never exposes it through `toArray()` (Requirement 5.3 / 15.3),
 *   - preserves the single-default-per-Workspace invariant (Requirement 5.4).
 */
class AiProviderTest extends PluginTestCase
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

    private function makeWorkspace(string $slugSuffix = ''): Workspace
    {
        $owner = $this->createBackendUser();

        return Workspace::create([
            'name'     => 'AiProvider Workspace',
            'slug'     => 'aiprov-ws-' . ($slugSuffix !== '' ? $slugSuffix . '-' : '') . uniqid(),
            'owner_id' => $owner->id,
        ]);
    }

    public function testApiKeyIsEncryptedAtRest(): void
    {
        $workspace = $this->makeWorkspace();

        $provider = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'OpenRouter',
            'driver'       => 'openrouter',
            'model'        => 'openai/gpt-4o',
            'api_key'      => 'sk-test',
            'is_default'   => true,
        ]);

        // The accessor must transparently decrypt the stored value: a fresh
        // model fetched from the database should expose the original key.
        $fresh = AiProvider::find($provider->id);
        $this->assertSame('sk-test', $fresh->api_key);

        // The raw column value, read directly via the query builder (no
        // accessors), must NOT equal the plaintext — it has been ciphered
        // by the `setApiKeyAttribute` mutator.
        $rawValue = DB::table('aero_masterads_ai_providers')
            ->where('id', $provider->id)
            ->value('api_key');

        $this->assertNotSame('sk-test', $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    public function testApiKeyHiddenFromArray(): void
    {
        $workspace = $this->makeWorkspace('hidden');

        $provider = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'OpenRouter Hidden',
            'driver'       => 'openrouter',
            'model'        => 'openai/gpt-4o',
            'api_key'      => 'sk-hidden',
            'is_default'   => false,
        ]);

        $array = $provider->toArray();

        $this->assertArrayNotHasKey('api_key', $array);
    }

    public function testSingleDefaultPerWorkspace(): void
    {
        $workspace = $this->makeWorkspace('default');

        $providerA = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Provider A',
            'driver'       => 'openrouter',
            'model'        => 'openai/gpt-4o',
            'api_key'      => 'sk-aaa',
            'is_default'   => true,
        ]);

        // Sanity: A was created with is_default=true.
        $this->assertTrue($providerA->fresh()->is_default);

        $providerB = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Provider B',
            'driver'       => 'openrouter',
            'model'        => 'anthropic/claude-3-sonnet',
            'api_key'      => 'sk-bbb',
            'is_default'   => true,
        ]);

        // Invariant: only one provider per workspace can be is_default=true.
        // Saving B with is_default=true must demote A to false.
        $this->assertFalse($providerA->fresh()->is_default);
        $this->assertTrue($providerB->fresh()->is_default);
    }
}
