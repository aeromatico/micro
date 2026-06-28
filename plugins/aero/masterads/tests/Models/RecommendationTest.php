<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\AiAnalysis;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\Recommendation;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use October\Rain\Exception\ValidationException;
use PluginTestCase;

/**
 * RecommendationTest — Validates the `action_type` enum constraint
 * (Requirement 6.7). Recommendations with an action outside the closed
 * enum must never be persisted because downstream `RecommendationApplier`
 * branches by that value.
 */
class RecommendationTest extends PluginTestCase
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

    public function testActionTypeEnumValidation(): void
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Rec Workspace',
            'slug'     => 'rec-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $provider = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Rec Provider',
            'driver'       => 'openrouter',
            'model'        => 'openai/gpt-4o',
            'api_key'      => 'sk-rec',
            'is_default'   => true,
        ]);

        $analysis = AiAnalysis::create([
            'workspace_id'   => $workspace->id,
            'ai_provider_id' => $provider->id,
            'target_type'    => 'campaign',
            'target_id'      => 1,
            'status'         => 'success',
        ]);

        $this->expectException(ValidationException::class);

        Recommendation::create([
            'ai_analysis_id' => $analysis->id,
            'action_type'    => 'bad_type',
            'severity'       => 'medium',
            'status'         => 'pending',
            'rationale'      => 'should never persist',
            'payload'        => [],
        ]);
    }
}
