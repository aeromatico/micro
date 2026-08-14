<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Ai;

use Aero\MasterAds\Classes\Exceptions\AiProviderException;
use Aero\MasterAds\Models\AiProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * OpenRouterClient — default LLM provider client targeting OpenRouter's
 * unified chat-completions endpoint.
 *
 * OpenRouter proxies every supported model (Claude, GPT-4o, Gemini, custom)
 * behind a single OpenAI-compatible REST surface, so the same client works
 * for every `Ai_Provider.model` identifier a Workspace_Admin configures
 * (Requirement 5.5).
 *
 * Responsibilities (Requirements 6.5 / 16.2):
 *  - Build the chat/completions payload with the configured model, the
 *    system + user messages and the engine-mandated defaults
 *    `temperature = 0.2` / `max_tokens = 4000`, allowing per-call overrides
 *    via `$options` and per-tenant overrides via `Ai_Provider.settings`.
 *  - When the caller supplies a `json_schema` option (the engine always
 *    does — the schema itself is embedded in the system prompt by
 *    {@see PromptBuilder}) request `response_format = json_object` so
 *    OpenRouter coerces Claude / GPT-4o into emitting parseable JSON.
 *  - Authenticate with the per-Workspace API key (decrypted transparently
 *    by `AiProvider::getApiKeyAttribute()`), and include the
 *    OpenRouter-mandatory `HTTP-Referer` / `X-Title` attribution headers
 *    (configurable through `provider.settings`).
 *  - Surface every upstream failure (network, 4xx/5xx, malformed JSON)
 *    as {@see AiProviderException} carrying provider / model /
 *    http_status / request_id and a truncated body excerpt so the
 *    Recommendation_Engine can persist a meaningful diagnostic
 *    `Ai_Analysis.error_message` (Requirement 6.9).
 *  - Compute `cost_usd` from a static per-million-token price table so
 *    {@see AiResponse::costUsd} can be persisted on `Ai_Analysis.cost_usd`
 *    alongside `tokens_used` (Requirement 16.2).
 *
 * Validates: Requirements 6.5, 16.2
 */
final class OpenRouterClient implements AiProviderInterface
{
    /**
     * Static price table in USD per 1,000,000 tokens. Keys MUST match the
     * canonical OpenRouter model identifier exactly. Models not in the
     * table fall back to {@see self::DEFAULT_PRICING} — a deliberately
     * conservative mid-tier price so cost is never silently reported as
     * zero (Requirement 16.2).
     */
    private const PRICING_USD_PER_MILLION = [
        'anthropic/claude-3.5-sonnet' => ['input' => 3.00,  'output' => 15.00],
        'anthropic/claude-opus-4'     => ['input' => 15.00, 'output' => 75.00],
        'openai/gpt-4o'               => ['input' => 5.00,  'output' => 15.00],
    ];

    /** Fallback price (USD per million tokens) when the model is unknown. */
    private const DEFAULT_PRICING = ['input' => 1.00, 'output' => 5.00];

    /** Max characters preserved from the upstream body when wrapping errors. */
    private const ERROR_BODY_EXCERPT_LIMIT = 1024;

    /** Per-request timeout in seconds — LLM completions can be slow. */
    private const DEFAULT_TIMEOUT_SECONDS = 90;

    /**
     * Underlying Guzzle client. Initialised once in the constructor —
     * either from the injected instance (tests, custom transports) or with
     * the default configuration pointing at the resolved OpenRouter base
     * URL with a 90-second timeout per request.
     */
    private readonly Client $http;

    /**
     * @param  AiProvider   $provider Configured tenant-scoped Ai_Provider.
     *                                Its `api_key` mutator decrypts the
     *                                stored ciphertext on read so this
     *                                class never sees plaintext credentials
     *                                at rest (Requirements 5.3, 15.1, 15.2).
     * @param  Client|null  $http     Pre-built Guzzle client for tests or
     *                                custom transports. When `null`, a
     *                                default client is created against
     *                                {@see self::resolveBaseUrl()} with a
     *                                90-second per-request timeout.
     */
    public function __construct(
        private readonly AiProvider $provider,
        ?Client $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => $this->resolveBaseUrl(),
            'timeout'  => self::DEFAULT_TIMEOUT_SECONDS,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  AiProviderInterface                                                */
    /* ------------------------------------------------------------------ */

    /**
     * {@inheritDoc}
     *
     * Builds an OpenAI-compatible chat-completions request, forwards it to
     * OpenRouter and returns the parsed {@see AiResponse} with raw text,
     * decoded JSON, usage counters and USD cost ready to persist on
     * `Ai_Analysis.tokens_used` / `cost_usd` (Requirement 16.2).
     *
     * The JSON content is parsed with {@see self::decodeJsonContent()},
     * which transparently strips any ```json … ``` markdown fences that
     * some models occasionally emit despite `response_format = json_object`.
     *
     * @throws AiProviderException When the upstream request fails
     *                             (network, 4xx/5xx, rate-limit, malformed
     *                             JSON, missing fields). The exception
     *                             carries `provider`, `model`,
     *                             `http_status`, `request_id` and a
     *                             truncated body excerpt for diagnostics
     *                             (Requirement 6.9).
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): AiResponse
    {
        $settings    = $this->provider->settings ?? [];
        $temperature = $options['temperature'] ?? $settings['temperature'] ?? 0.2;
        $maxTokens   = $options['max_tokens']  ?? $settings['max_tokens']  ?? 4000;

        $payload = [
            'model'    => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ];

        // PromptBuilder already embeds the JSON schema in the system prompt
        // (Requirement 6.5); here we only need to flip OpenRouter into
        // JSON-output mode. OpenRouter accepts this `response_format` shape
        // for Claude / GPT-4o models.
        if (isset($options['json_schema'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . (string) $this->provider->api_key,
            'HTTP-Referer'  => (string) ($settings['http_referer'] ?? 'https://masterads.local'),
            'X-Title'       => (string) ($settings['x_title']      ?? 'Master Ads'),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        try {
            $response = $this->http->request('POST', 'chat/completions', [
                'headers' => $headers,
                'json'    => $payload,
            ]);
        } catch (RequestException $e) {
            throw $this->wrap('OpenRouter request failed', $e);
        } catch (GuzzleException $e) {
            throw $this->wrap('OpenRouter transport error', $e);
        }

        $rawBody = (string) $response->getBody();

        try {
            $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw $this->wrap('OpenRouter response is not valid JSON', $e, [
                'http_status' => $response->getStatusCode(),
                'request_id'  => $this->headerValue($response, 'x-request-id'),
                'body'        => $this->truncate($rawBody),
            ]);
        }

        if (!is_array($body) || !isset($body['choices'][0]['message']['content'])) {
            throw new AiProviderException(
                'OpenRouter response missing choices[0].message.content',
                $response->getStatusCode(),
                null,
                [
                    'provider'    => $this->provider->driver,
                    'model'       => $this->model(),
                    'http_status' => $response->getStatusCode(),
                    'request_id'  => $this->headerValue($response, 'x-request-id'),
                    'body'        => $this->truncate($rawBody),
                ]
            );
        }

        $content = (string) $body['choices'][0]['message']['content'];

        try {
            $parsed = $this->decodeJsonContent($content);
        } catch (JsonException $e) {
            throw $this->wrap('OpenRouter completion content is not valid JSON', $e, [
                'http_status' => $response->getStatusCode(),
                'request_id'  => $this->headerValue($response, 'x-request-id'),
                'content'     => $this->truncate($content),
            ]);
        }

        $promptTokens     = (int) ($body['usage']['prompt_tokens']     ?? 0);
        $completionTokens = (int) ($body['usage']['completion_tokens'] ?? 0);
        $costUsd          = $this->estimateCost($promptTokens, $completionTokens);

        return new AiResponse(
            raw: $content,
            parsed: $parsed,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            costUsd: $costUsd,
            model: $this->model(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function model(): string
    {
        return (string) $this->provider->model;
    }

    /**
     * {@inheritDoc}
     *
     * Uses {@see self::PRICING_USD_PER_MILLION} (input / output rate per
     * million tokens). Models not in the table fall back to
     * {@see self::DEFAULT_PRICING}. Result is rounded to 6 decimals so
     * `Ai_Analysis.cost_usd` (DECIMAL(10,6) per the schema) can store it
     * without loss (Requirement 16.2).
     */
    public function estimateCost(int $promptTokens, int $completionTokens): float
    {
        $pricing = self::PRICING_USD_PER_MILLION[$this->model()] ?? self::DEFAULT_PRICING;

        $promptCost     = ($promptTokens     / 1_000_000) * $pricing['input'];
        $completionCost = ($completionTokens / 1_000_000) * $pricing['output'];

        return round($promptCost + $completionCost, 6);
    }

    /* ------------------------------------------------------------------ */
    /*  Internals                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve the OpenRouter base URL with a clear precedence chain:
     *   1. `provider.settings.base_url`  (per-tenant override, Req. 5.5)
     *   2. `config('services.master_ads_openrouter.base_url')`
     *   3. Hardcoded fallback `https://openrouter.ai/api/v1/`.
     *
     * Trailing slash is normalised so Guzzle's `base_uri` resolution
     * concatenates `'chat/completions'` correctly without producing
     * `//chat/completions`.
     */
    private function resolveBaseUrl(): string
    {
        $settings = $this->provider->settings ?? [];
        $base = $settings['base_url']
            ?? config(
                'services.master_ads_openrouter.base_url',
                'https://openrouter.ai/api/v1/'
            );

        return rtrim((string) $base, '/') . '/';
    }

    /**
     * Decode the model's `content` field, tolerating optional ```json …
     * ``` markdown fences that some models occasionally emit despite
     * `response_format = json_object`. Strictness is preserved on truly
     * malformed payloads — they bubble up as {@see JsonException} to the
     * caller, which then wraps them in {@see AiProviderException}.
     *
     * @return array<int|string,mixed>
     *
     * @throws JsonException When the content cannot be decoded into an
     *                       array even after fence stripping.
     */
    private function decodeJsonContent(string $content): array
    {
        $trimmed = trim($content);

        // First attempt: decode the content as-is.
        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (JsonException) {
            // Fall through to fence stripping.
        }

        // Second attempt: strip ```json / ``` markdown fences, then decode.
        $stripped = preg_replace('/^```(?:json)?\s*|\s*```$/im', '', $trimmed) ?? $trimmed;
        $stripped = trim($stripped);

        $decoded = json_decode($stripped, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new JsonException('Decoded content is not a JSON object/array');
        }

        return $decoded;
    }

    /**
     * Wrap a transport / parse failure into an {@see AiProviderException}
     * carrying full diagnostic context (provider, model, HTTP status,
     * upstream `x-request-id` and a truncated body excerpt) so the engine
     * can persist a meaningful `Ai_Analysis.error_message`
     * (Requirements 6.9, 16.4).
     *
     * @param  array<string,mixed> $extra Overrides / extra context keys
     *                                    such as `body`, `content`,
     *                                    `http_status` or `request_id`
     *                                    discovered outside the
     *                                    Guzzle response chain.
     */
    private function wrap(string $message, Throwable $previous, array $extra = []): AiProviderException
    {
        $httpStatus = null;
        $requestId  = null;
        $body       = null;

        if ($previous instanceof RequestException && $previous->hasResponse()) {
            $response   = $previous->getResponse();
            $httpStatus = $response->getStatusCode();
            $requestId  = $this->headerValue($response, 'x-request-id');
            $body       = $this->truncate((string) $response->getBody());
        }

        $context = [
            'provider'    => $this->provider->driver,
            'model'       => $this->model(),
            'http_status' => $httpStatus,
            'request_id'  => $requestId,
            'body'        => $body,
        ];

        // Caller-supplied keys override the defaults.
        foreach ($extra as $k => $v) {
            $context[$k] = $v;
        }

        return new AiProviderException(
            sprintf('%s: %s', $message, $previous->getMessage()),
            (int) ($context['http_status'] ?? 0),
            $previous,
            $context,
        );
    }

    /**
     * Read a single header line from a PSR-7 response, normalising the
     * empty-string-not-present case to `null` for cleaner context arrays.
     */
    private function headerValue(ResponseInterface $response, string $name): ?string
    {
        $line = $response->getHeaderLine($name);
        return $line === '' ? null : $line;
    }

    /**
     * Truncate a body excerpt to {@see self::ERROR_BODY_EXCERPT_LIMIT}
     * characters, appending an ellipsis when content is dropped. Keeps
     * the structured context payload small enough to safely log and
     * persist on `Ai_Analysis.error_message`.
     */
    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::ERROR_BODY_EXCERPT_LIMIT) {
            return $text;
        }
        return mb_substr($text, 0, self::ERROR_BODY_EXCERPT_LIMIT) . '…';
    }
}
