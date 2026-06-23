<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Ai;

/**
 * AiProviderInterface
 *
 * Contract implemented by every LLM provider client (OpenRouter, OpenAI,
 * Anthropic, custom). Decouples the Recommendation_Engine from any concrete
 * vendor SDK so providers can be swapped per-Workspace at runtime.
 *
 * The Recommendation_Engine resolves the concrete implementation through the
 * `Ai_Provider` model: when `force_provider` is not supplied the provider
 * with `is_default = true` of the active Workspace is selected.
 *
 * Validates: Requirements 5.6, 16.2
 */
interface AiProviderInterface
{
    /**
     * Execute a chat-completion call expecting structured JSON output.
     *
     * @param  string               $systemPrompt Role/format instruction for the LLM.
     * @param  string               $userPrompt   Context payload with KPI snapshot.
     * @param  array<string,mixed>  $options      Tuning parameters such as
     *                                            ['temperature' => 0.2,
     *                                             'max_tokens'  => 4000,
     *                                             'json_schema' => [...]].
     *
     * @return AiResponse Structured DTO carrying `raw`, `parsed`,
     *                    `promptTokens`, `completionTokens`, `costUsd`
     *                    and `model` (Requirement 16.2).
     *
     * @throws \Aero\MasterAds\Classes\Exceptions\AiProviderException
     *         When the upstream call fails (network, rate-limit, 5xx) or the
     *         response body cannot be parsed against the requested schema.
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): AiResponse;

    /**
     * Returns the model identifier in use (e.g. "anthropic/claude-3.5-sonnet").
     *
     * Used by Recommendation_Engine when persisting `Ai_Analysis.raw_response`
     * and by the AiAnalyses list controller to expose the `provider.model`
     * column (Requirement 16.3).
     */
    public function model(): string;

    /**
     * USD cost estimation for the given token counts using the provider's
     * internal price table.
     *
     * Invoked by Recommendation_Engine when the analysis terminates to fill
     * `Ai_Analysis.cost_usd` together with `tokens_used` (Requirement 16.2).
     *
     * @param  int $promptTokens     Tokens consumed by the prompt payload.
     * @param  int $completionTokens Tokens emitted by the completion.
     * @return float                 Estimated cost in USD (non-negative).
     */
    public function estimateCost(int $promptTokens, int $completionTokens): float;
}
