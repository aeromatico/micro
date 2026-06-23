<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Ai;

use Aero\MasterAds\Classes\Ai\PromptBuilder;
use PHPUnit\Framework\TestCase;

/**
 * PromptBuilderTest — covers the pure prompt composition logic in
 * {@see PromptBuilder}.
 *
 * The class has zero side effects (no DB, no HTTP, no filesystem) so the
 * suite extends `\PHPUnit\Framework\TestCase` directly instead of the
 * heavier `PluginTestCase`. Each test focuses on a single observable
 * invariant of the rendered prompt — the embedded enums, the JSON schema,
 * the lookback aggregate (Requirement 6.4) and the operator-facing metrics
 * table — so the LLM contract surface is fully covered without coupling
 * the assertions to incidental wording.
 *
 * Validates: Requirements 6.4, 6.5
 */
class PromptBuilderTest extends TestCase
{
    /**
     * The system prompt MUST advertise every canonical action_type so the
     * LLM never invents an action the downstream
     * {@see RecommendationValidator} would discard (Requirement 6.5).
     */
    public function testSystemPromptIncludesAllowedActionTypes(): void
    {
        $prompt = (new PromptBuilder())->system('campaign');

        $this->assertIsString($prompt);
        $this->assertNotSame('', $prompt, 'system prompt must be non-empty.');

        foreach (PromptBuilder::ACTION_TYPES as $action) {
            $this->assertStringContainsString(
                $action,
                $prompt,
                sprintf('System prompt must declare the allowed action_type "%s".', $action)
            );
        }
    }

    /**
     * The system prompt MUST embed the canonical RECOMMENDATION_SCHEMA so
     * the LLM has the exact JSON contract in context (Requirement 6.5).
     */
    public function testSystemPromptIncludesSchema(): void
    {
        $prompt = (new PromptBuilder())->system('campaign');

        $schemaJson = json_encode(
            PromptBuilder::RECOMMENDATION_SCHEMA,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $this->assertIsString($schemaJson);
        $this->assertStringContainsString(
            (string) $schemaJson,
            $prompt,
            'System prompt must embed the JSON schema verbatim.'
        );
    }

    /**
     * The user prompt MUST advertise the lookback window explicitly so the
     * model judges trends against the correct horizon (Requirement 6.4).
     */
    public function testUserPromptIncludesLookbackDays(): void
    {
        $target = [
            'id'     => 'cmp_lookback',
            'name'   => 'Lookback Campaign',
            'status' => 'ACTIVE',
        ];

        $prompt = (new PromptBuilder())->user(
            $target,
            ['impressions' => 1000, 'clicks' => 50],
            ['ctr' => 5.0],
            ['lookback_days' => 21]
        );

        $this->assertStringContainsString(
            '21 days',
            $prompt,
            'User prompt must mention the requested lookback window.'
        );
    }

    /**
     * The user prompt MUST surface the operator-facing KPI tables so the
     * LLM can cite specific metrics in its rationale.
     */
    public function testUserPromptIncludesMetricsTable(): void
    {
        $target = [
            'id'     => 'cmp_metrics',
            'name'   => 'Metrics Campaign',
            'status' => 'ACTIVE',
        ];

        $prompt = (new PromptBuilder())->user(
            $target,
            ['impressions' => 10000, 'clicks' => 250, 'spend' => 120.5],
            ['ctr' => 2.5, 'cpc' => 0.48],
            ['lookback_days' => 14]
        );

        $this->assertStringContainsString('Impressions', $prompt, 'Raw metrics table must list Impressions.');
        $this->assertStringContainsString('CTR', $prompt, 'Derived KPIs table must list CTR.');
    }

    /**
     * Unknown target types MUST fall back to "campaign" so the prompt
     * never carries a hallucinated entity name into the LLM context.
     */
    public function testNormalizesUnknownTargetType(): void
    {
        $prompt = (new PromptBuilder())->system('garbage');

        $this->assertStringContainsString(
            'campaign',
            $prompt,
            'Unknown target types must be normalised to "campaign".'
        );
    }

    /**
     * When the target is supplied as a plain associative array (the engine
     * occasionally passes pre-serialised payloads instead of Eloquent
     * models) PromptBuilder MUST extract its metadata fields verbatim.
     */
    public function testHandlesArrayTargetMetadata(): void
    {
        $target = [
            'id'     => 'cmp_1',
            'name'   => 'My Campaign',
            'status' => 'ACTIVE',
        ];

        $prompt = (new PromptBuilder())->user(
            $target,
            ['impressions' => 500, 'clicks' => 25],
            ['ctr' => 5.0],
            ['lookback_days' => 14]
        );

        $this->assertStringContainsString('cmp_1', $prompt, 'Target id must render in the metadata block.');
        $this->assertStringContainsString('My Campaign', $prompt, 'Target name must render in the metadata block.');
        $this->assertStringContainsString('ACTIVE', $prompt, 'Target status must render in the metadata block.');
    }
}
