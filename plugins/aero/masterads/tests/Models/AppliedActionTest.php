<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\AiAnalysis;
use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\AppliedAction;
use Aero\MasterAds\Models\Recommendation;
use Aero\MasterAds\Models\Workspace;
use Backend\Models\User as BackendUser;
use October\Rain\Exception\ApplicationException;
use PluginTestCase;

/**
 * AppliedActionTest — Validates the append-only audit-trail contract: once
 * persisted, both `update()` and `delete()` raise `ApplicationException`.
 * This is what powers Property P5 (audit trail completeness, Requirements
 * 7.10, 7.11, 8.1, 8.2, 8.3).
 */
class AppliedActionTest extends PluginTestCase
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
     * Build the full chain Workspace → AiProvider → AiAnalysis →
     * Recommendation → BackendUser, then return a freshly-saved
     * AppliedAction tied to all of them.
     */
    private function bootAppliedAction(): AppliedAction
    {
        $owner = $this->createBackendUser();

        $workspace = Workspace::create([
            'name'     => 'Applied Workspace',
            'slug'     => 'applied-ws-' . uniqid(),
            'owner_id' => $owner->id,
        ]);

        $provider = AiProvider::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Applied Provider',
            'driver'       => 'openrouter',
            'model'        => 'openai/gpt-4o',
            'api_key'      => 'sk-applied',
            'is_default'   => true,
        ]);

        $analysis = AiAnalysis::create([
            'workspace_id'   => $workspace->id,
            'ai_provider_id' => $provider->id,
            'target_type'    => 'campaign',
            'target_id'      => 1,
            'status'         => 'success',
        ]);

        $recommendation = Recommendation::create([
            'ai_analysis_id' => $analysis->id,
            'action_type'    => 'pause',
            'severity'       => 'medium',
            'status'         => 'pending',
            'rationale'      => 'Spend without conversions',
            'payload'        => [],
        ]);

        return AppliedAction::create([
            'recommendation_id' => $recommendation->id,
            'applied_by'        => $owner->id,
            'success'           => true,
            'before_state'      => ['status' => 'ACTIVE'],
            'after_state'       => ['status' => 'PAUSED'],
            'meta_response'     => ['ok' => true],
        ]);
    }

    public function testAppendOnlyBlocksUpdate(): void
    {
        $applied = $this->bootAppliedAction();

        $this->expectException(ApplicationException::class);

        // The beforeUpdate hook must fire before the row is written.
        $applied->update(['success' => false]);
    }

    public function testAppendOnlyBlocksDelete(): void
    {
        $applied = $this->bootAppliedAction();

        $this->expectException(ApplicationException::class);

        // Likewise for delete — the audit trail must survive the lifetime
        // of its parent Recommendation.
        $applied->delete();
    }
}
