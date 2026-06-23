<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Ai;

/**
 * AiResponse
 *
 * Immutable DTO returned by {@see AiProviderInterface::complete()}.
 *
 * Carries both the raw textual response (for audit / reproducibility,
 * persisted in `Ai_Analysis.raw_response`) and its parsed JSON form, plus
 * the usage counters that feed `Ai_Analysis.tokens_used` and
 * `Ai_Analysis.cost_usd` (Requirement 16.2).
 *
 * The Recommendation_Engine uses `$parsed` to materialise Recommendations
 * after schema validation, and `$model` to reflect which provider produced
 * the analysis on the `AiAnalyses` list (Requirement 16.3).
 *
 * Validates: Requirements 5.6, 16.2
 */
final class AiResponse
{
    /**
     * @param string              $raw              Full raw response text emitted by the LLM.
     * @param array<string,mixed> $parsed           JSON-decoded structured output (already
     *                                              schema-validated when produced by
     *                                              {@see AiProviderInterface::complete()}).
     * @param int                 $promptTokens    Tokens consumed by the prompt payload.
     * @param int                 $completionTokens Tokens emitted by the completion.
     * @param float               $costUsd         Estimated cost in USD computed via
     *                                              {@see AiProviderInterface::estimateCost()}
     *                                              (Requirement 16.2).
     * @param string              $model           Identifier of the model that produced the
     *                                              response (e.g. "anthropic/claude-3.5-sonnet").
     */
    public function __construct(
        public readonly string $raw,
        public readonly array $parsed,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly float $costUsd,
        public readonly string $model
    ) {
    }
}
