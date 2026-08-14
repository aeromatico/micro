<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\AiAnalysis;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use PluginTestCase;

/**
 * AiAnalysisTest — Validates SoftDelete behaviour (Requirement 8.6, the
 * audit trail must survive logical deletes) and the JSONable round-trip of
 * the prompt / response / metrics-snapshot columns (Requirement 8.5).
 */
class AiAnalysisTest extends PluginTestCase
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
     * Build a Workspace + AiProvider pair so AiAnalysis FK rules are happy.
     */
    private function bootScaffolding(): array
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'AI Analysis Workspace',
            'slug'     => 'ai-analysis-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $provider = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'AI Provider',
            'driver'       => 'openrouter',
            'model'        => 'openai/gpt-4o',
            'api_key'      => 'sk-analysis-test',
            'is_default'   => true,
        ]);

        return [$workspace, $provider];
    }

    public function testSoftDelete(): void
    {
        [$workspace, $provider] = $this->bootScaffolding();

        $analysis = AiAnalysis::create([
            'workspace_id'   => $workspace->id,
            'ai_provider_id' => $provider->id,
            'target_type'    => 'campaign',
            'target_id'      => 1,
            'status'         => 'success',
        ]);

        $analysis->delete();

        // The default scope hides soft-deleted rows.
        $this->assertNull(AiAnalysis::find($analysis->id));

        // ...but the row still exists in the table.
        $trashed = AiAnalysis::withTrashed()->find($analysis->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
    }

    public function testJsonableFields(): void
    {
        [$workspace, $provider] = $this->bootScaffolding();

        $metrics  = ['impressions' => 1000, 'clicks' => 25, 'spend' => 12.34];
        $prompt   = ['system' => 'You are a Meta Ads analyst.', 'user' => 'Hello'];
        $response = ['model' => 'gpt-4o', 'choices' => [['index' => 0]]];

        $analysis = AiAnalysis::create([
            'workspace_id'     => $workspace->id,
            'ai_provider_id'   => $provider->id,
            'target_type'      => 'adset',
            'target_id'        => 7,
            'status'           => 'success',
            'metrics_snapshot' => $metrics,
            'prompt_payload'   => $prompt,
            'raw_response'     => $response,
        ]);

        // Round-trip via the DB so we exercise the jsonable decoder.
        $reloaded = AiAnalysis::find($analysis->id);

        $this->assertIsArray($reloaded->metrics_snapshot);
        $this->assertIsArray($reloaded->prompt_payload);
        $this->assertIsArray($reloaded->raw_response);

        $this->assertSame($metrics, $reloaded->metrics_snapshot);
        $this->assertSame($prompt, $reloaded->prompt_payload);
        $this->assertSame($response, $reloaded->raw_response);
    }
}
