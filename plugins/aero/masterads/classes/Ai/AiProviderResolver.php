<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Ai;

use Aero\MasterAds\Models\AiProvider;
use Aero\MasterAds\Models\Workspace;
use Aero\MasterAds\Classes\Exceptions\AiProviderException;

/**
 * AiProviderResolver — selects and instantiates the concrete
 * {@see AiProviderInterface} implementation that the Recommendation_Engine
 * must use for an analysis run.
 *
 * Resolution strategy (Requirement 5.6):
 *   1. If `$forceProviderId` is supplied, load that exact row. A missing id
 *      is a hard error (`AiProviderException`) — callers must not pass an
 *      unknown id silently.
 *   2. Otherwise, prefer the Workspace-owned provider with `is_default = true`.
 *      This is the documented default path when an analysis is invoked
 *      without the `force_provider` option (Requirement 5.6).
 *   3. Otherwise, fall back to the first provider belonging to the same
 *      Workspace (ordered by `id`, i.e. creation order). This covers the
 *      corner case where the operator forgot to mark a default.
 *   4. Otherwise, fall back to a globally-shared provider — a row whose
 *      `workspace_id IS NULL` and whose `is_default = true`. This lets a
 *      platform administrator pre-seed a default OpenRouter key for every
 *      tenant in an MVP deployment.
 *   5. If none of the above match, throw {@see AiProviderException} so the
 *      Recommendation_Engine can reject the analysis "with an explicit
 *      error and NOT create the Ai_Analysis" (Requirement 5.7).
 *
 * Driver-to-client mapping (MVP):
 *   - `openrouter`, `openai`, `anthropic`, `custom`  →  {@see OpenRouterClient}.
 *
 * For the MVP every driver routes through `OpenRouterClient`, which already
 * supports a `base_url` override read from `AiProvider.settings`
 * (Requirement 5.5). Concrete vendor-specific adapters
 * (`AnthropicDirectClient`, `OpenAiDirectClient`) are a planned future
 * iteration once direct SDKs justify the extra surface area.
 *
 * All queries are issued with `withoutGlobalScope('tenant')` so the resolver
 * behaves deterministically regardless of the calling context (HTTP backend
 * controller, queue worker, console command). The caller is responsible for
 * having authorised the `$workspace` argument before invoking the resolver.
 *
 * Validates: Requirements 5.6, 5.7, 6.9
 */
class AiProviderResolver
{
    /**
     * Resolve a concrete provider client for the given Workspace.
     *
     * @param  Workspace $workspace        Tenant context for the analysis.
     * @param  int|null  $forceProviderId  When non-null, bypass the default
     *                                     election and load this exact
     *                                     `AiProvider.id` (mirrors the
     *                                     `options.force_provider` flag
     *                                     consumed by Recommendation_Engine,
     *                                     Requirement 5.6).
     *
     * @return AiProviderInterface         A ready-to-use client whose
     *                                     `complete()` / `model()` /
     *                                     `estimateCost()` methods will be
     *                                     called by the Recommendation_Engine.
     *
     * @throws AiProviderException         When `$forceProviderId` is supplied
     *                                     but the row does not exist, when no
     *                                     provider is available for the
     *                                     Workspace (Requirement 5.7), or
     *                                     when the persisted `driver` value
     *                                     is not recognised.
     */
    public function resolve(Workspace $workspace, ?int $forceProviderId = null): AiProviderInterface
    {
        $provider = $forceProviderId !== null
            ? $this->loadForced($forceProviderId)
            : $this->electDefault($workspace);

        return $this->instantiate($provider);
    }

    /**
     * Load the exact AiProvider requested via `force_provider`.
     *
     * Bypasses the `tenant` global scope on AiProvider so the lookup is
     * unaffected by the current Backend_User's workspace membership: the
     * caller (Recommendation_Engine) has already authorised the action by
     * accepting the option.
     *
     * @throws AiProviderException when the id has no matching row.
     */
    private function loadForced(int $providerId): AiProvider
    {
        /** @var AiProvider|null $provider */
        $provider = AiProvider::withoutGlobalScope('tenant')->find($providerId);

        if ($provider === null) {
            throw new AiProviderException(
                "Forced AI provider not found",
                0,
                null,
                ['provider_id' => $providerId]
            );
        }

        return $provider;
    }

    /**
     * Elect the AiProvider to use when no `force_provider` was supplied,
     * applying the cascade described in the class-level docblock.
     *
     * @throws AiProviderException when no candidate row matches at any tier
     *                             (Requirement 5.7).
     */
    private function electDefault(Workspace $workspace): AiProvider
    {
        // Tier 1: workspace-scoped default.
        $provider = AiProvider::withoutGlobalScope('tenant')
            ->where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->first();

        if ($provider !== null) {
            return $provider;
        }

        // Tier 2: any provider belonging to the workspace (first by id).
        $provider = AiProvider::withoutGlobalScope('tenant')
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->first();

        if ($provider !== null) {
            return $provider;
        }

        // Tier 3: globally-shared default (workspace_id IS NULL).
        $provider = AiProvider::withoutGlobalScope('tenant')
            ->whereNull('workspace_id')
            ->where('is_default', true)
            ->first();

        if ($provider !== null) {
            return $provider;
        }

        throw new AiProviderException(
            "No AI provider configured for workspace {$workspace->id}",
            0,
            null,
            ['workspace_id' => $workspace->id]
        );
    }

    /**
     * Map an `AiProvider.driver` value to its concrete
     * {@see AiProviderInterface} implementation.
     *
     * TODO: Future iterations will introduce dedicated adapters
     *       — `AnthropicDirectClient` and `OpenAiDirectClient` — once
     *       vendor-specific features (Anthropic vision, OpenAI tool calls)
     *       outgrow what OpenRouter exposes through its pass-through API.
     *       For the MVP every supported driver is routed through
     *       {@see OpenRouterClient}, which already honours a `base_url`
     *       override from `AiProvider.settings` (Requirement 5.5) so the
     *       'openai', 'anthropic' and 'custom' drivers can transparently
     *       point at their respective endpoints.
     *
     * @throws AiProviderException when the driver string is not one of the
     *         four supported values (defence-in-depth against schema drift,
     *         even though the model validates the enum at write time).
     */
    private function instantiate(AiProvider $provider): AiProviderInterface
    {
        $driver = (string) $provider->driver;

        switch ($driver) {
            case 'openrouter':
            case 'openai':
            case 'anthropic':
            case 'custom':
                // TODO: swap for AnthropicDirectClient / OpenAiDirectClient
                //       once those adapters land. The constructor signature
                //       is part of Task 8.2 (`OpenRouterClient($provider)`).
                return new OpenRouterClient($provider);

            default:
                throw new AiProviderException(
                    "Unsupported AI provider driver: {$driver}",
                    0,
                    null,
                    [
                        'provider_id' => $provider->id,
                        'driver'      => $driver,
                    ]
                );
        }
    }
}
