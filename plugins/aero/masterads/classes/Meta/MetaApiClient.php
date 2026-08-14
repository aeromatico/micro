<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Meta;

use Aero\MasterAds\Classes\Exceptions\MetaApiException;
use Aero\MasterAds\Classes\Exceptions\MetaApiRateLimitException;
use Aero\MasterAds\Models\MetaAccount;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MetaApiClient — HTTP wrapper for the Meta Graph API (v19+).
 *
 * Owns a single `MetaAccount` and exposes the two access patterns every
 * higher-level service in the plugin needs against `graph.facebook.com`:
 *
 *  - `getPaginated()` — a PHP `Generator` that walks `data[]` page by page,
 *    following the `paging.next` absolute URL until exhausted. The consumer
 *    can break out of the loop early (PIPE_BREAK semantics) without
 *    wasting further requests, because the generator only fetches the
 *    next page after the previous one has been fully drained.
 *
 *  - `call()` — single GET/POST/DELETE request with exponential backoff on
 *    HTTP 429 or Meta error sub-code 613 (application request-limit). Waits
 *    follow the schedule 1, 2, 4, 8 and 16 seconds, for a hard cap of 5
 *    retries (6 total attempts). After the 5th retry still fails the call
 *    surfaces a {@see MetaApiRateLimitException} with `retriesUsed = 5` so
 *    the owning job can be left in `failed` state.
 *
 * Token rotation is handled transparently: before every request the client
 * checks {@see MetaAccount::expiresWithinDays()} and, if the access token
 * is within 7 days of expiration, delegates to `MetaTokenRefresher`
 * resolved lazily through the container. The forward reference via
 * `app(MetaTokenRefresher::class)` lets this file load even when the
 * refresher class (task 7.2) is not yet present on disk.
 *
 * Tokens are read from the `MetaAccount` model, which decrypts them in
 * its mutator — they never touch this class as ciphertext.
 *
 * All retries are logged via `Log::warning` with a per-call
 * `correlation_id` (UUID v4) so an operator can stitch together the
 * sequence of backoffs that preceded a final success or rate-limit
 * failure.
 *
 * Validates: Requirements 3.1, 3.6, 3.7, 14.1, 14.3, 15.6 (master-ads spec).
 */
class MetaApiClient
{
    /**
     * Maximum number of retries on a rate-limited response (429 / 613).
     * The initial attempt is not counted; total attempts on persistent
     * failure is MAX_RETRIES + 1 = 6.
     */
    private const MAX_RETRIES = 5;

    /**
     * Sleep schedule (in seconds) applied BEFORE each retry, indexed by
     * `retries_used` so the wait grows 1 → 2 → 4 → 8 → 16. Cumulative
     * worst-case wall-time waiting is 31 s on a persistently throttled
     * endpoint.
     */
    private const BACKOFF_SCHEDULE_SECONDS = [1, 2, 4, 8, 16];

    /**
     * Underlying Guzzle client. Initialised once in the constructor — either
     * from the injected instance (tests, custom transports) or with the
     * project's default configuration pointing at the configured Graph API
     * version and a 30-second timeout per request.
     */
    private readonly Client $http;

    /**
     * @param  MetaAccount  $account  Connected ad account whose tokens
     *                                authenticate every request. Tokens
     *                                are read through the model's decrypt
     *                                accessor — never persisted in plain
     *                                text inside this class.
     * @param  Client|null  $http     Optional pre-built Guzzle client. When
     *                                `null` the client is created against
     *                                `services.master_ads_meta.api_version`
     *                                (defaulting to `v19.0`).
     */
    public function __construct(
        private readonly MetaAccount $account,
        ?Client $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => sprintf(
                'https://graph.facebook.com/%s/',
                config('services.master_ads_meta.api_version', 'v19.0')
            ),
            'timeout' => 30,
        ]);
    }

    /**
     * Walk a paginated Graph API endpoint, yielding every item in `data[]`.
     *
     * The generator starts at `$endpoint?<params>` (resolved against the
     * Guzzle `base_uri`) and, after draining the current page's `data`
     * array, follows `paging.next` — which Meta returns as an absolute URL
     * pre-signed with the access token and all original query parameters.
     *
     * The loop terminates as soon as a page's `paging.next` is missing or
     * empty. If the consumer stops iterating early (e.g. `break;` inside
     * `foreach`) PHP destroys the generator and no further HTTP requests
     * are issued — this is exactly the PIPE_BREAK semantics Requirement
     * 14.1 mandates to avoid loading the full response into memory.
     *
     * Rate-limit handling is inherited from {@see self::requestWithBackoff()}.
     *
     * @param  string                $endpoint  Relative Graph endpoint,
     *                                          e.g. `"act_123456/campaigns"`.
     * @param  array<string,mixed>   $params    Query parameters; the access
     *                                          token is added automatically.
     * @return Generator<int, array<string,mixed>>
     *
     * Validates: Requirements 3.1, 14.1.
     */
    public function getPaginated(string $endpoint, array $params = []): Generator
    {
        $this->refreshTokenIfNeeded();

        // The access token is required on the first request; the
        // `paging.next` URL that Meta returns already embeds it for
        // subsequent pages.
        $params['access_token'] = $this->account->access_token;

        $url   = $endpoint;
        $query = $params;

        while (true) {
            $response = $this->requestWithBackoff('GET', $url, ['query' => $query]);

            foreach (($response['data'] ?? []) as $item) {
                yield $item;
            }

            $next = $response['paging']['next'] ?? null;
            if (!is_string($next) || $next === '') {
                break;
            }

            // Absolute next URL already carries every query param + token,
            // so we must not append anything else.
            $url   = $next;
            $query = [];
        }
    }

    /**
     * Perform a single Graph API request with exponential-backoff retry on
     * rate-limit responses.
     *
     * The access token is added automatically: for `GET` requests it is
     * appended as a query parameter, for any other verb (`POST`, `DELETE`,
     * `PUT`) it travels as a form field — matching the Graph API
     * convention for write endpoints.
     *
     * Returns the decoded JSON body as an associative array. On any
     * non-rate-limit failure (4xx/5xx, transport errors, malformed JSON)
     * a {@see MetaApiException} is thrown carrying the endpoint, method
     * and last response body in its `$context`. After exhausting the
     * 5 retries on a persistent 429/613 a {@see MetaApiRateLimitException}
     * is thrown with `retriesUsed = 5`.
     *
     * @param  string               $method   HTTP verb (`GET`, `POST`, …).
     * @param  string               $endpoint Relative Graph endpoint.
     * @param  array<string,mixed>  $params   Verb-appropriate parameters.
     * @return array<string,mixed>            Decoded JSON response body.
     *
     * @throws MetaApiException
     * @throws MetaApiRateLimitException
     *
     * Validates: Requirements 3.6, 3.7, 14.3, 15.6.
     */
    public function call(string $method, string $endpoint, array $params = []): array
    {
        $this->refreshTokenIfNeeded();

        $params['access_token'] = $this->account->access_token;
        $methodUpper = strtoupper($method);

        $options = $methodUpper === 'GET'
            ? ['query' => $params]
            : ['form_params' => $params];

        return $this->requestWithBackoff($methodUpper, $endpoint, $options);
    }

    /**
     * Issue a single HTTP request, retrying on 429 / sub-code 613.
     *
     * Loop invariants (also documented in the design):
     *  - `retries ≤ MAX_RETRIES (=5)` at the top of every iteration.
     *  - Sleep is applied BEFORE the retry attempt, never after the final
     *    failure — so a caller seeing the exception observes at most
     *    `sum(BACKOFF_SCHEDULE_SECONDS) = 31` seconds of waiting.
     *  - A non-rate-limit error short-circuits immediately, never sleeping.
     *
     * @param  array<string,mixed> $options Guzzle request options.
     * @return array<string,mixed>          Decoded JSON body on success.
     *
     * @throws MetaApiException
     * @throws MetaApiRateLimitException
     */
    private function requestWithBackoff(string $method, string $url, array $options): array
    {
        $correlationId = (string) Str::uuid();
        $retries       = 0;
        $lastException = null;
        $lastBody      = null;
        $lastStatus    = 0;

        while (true) {
            try {
                $response = $this->http->request($method, $url, $options);
                $body     = (string) $response->getBody();
                $decoded  = json_decode($body, true);

                if (!is_array($decoded)) {
                    throw new MetaApiException(
                        sprintf('Meta API returned non-JSON body for %s %s', $method, $url),
                        $response->getStatusCode(),
                        null,
                        [
                            'endpoint'       => $url,
                            'method'         => $method,
                            'http_status'    => $response->getStatusCode(),
                            'response_body'  => $body,
                            'correlation_id' => $correlationId,
                        ]
                    );
                }

                return $decoded;
            } catch (RequestException $e) {
                $lastException = $e;
                $lastStatus    = $e->getResponse()?->getStatusCode() ?? 0;
                $lastBody      = $e->getResponse() !== null
                    ? (string) $e->getResponse()->getBody()
                    : null;
                $subcode = $this->extractMetaSubcode($lastBody);

                $isRateLimit = $lastStatus === 429 || $subcode === 613;
                if (!$isRateLimit) {
                    throw new MetaApiException(
                        sprintf('Meta API error %d on %s %s', $lastStatus, $method, $url),
                        $lastStatus,
                        $e,
                        [
                            'endpoint'           => $url,
                            'method'             => $method,
                            'http_status'        => $lastStatus,
                            'meta_error_subcode' => $subcode,
                            'response_body'      => $lastBody,
                            'correlation_id'     => $correlationId,
                        ]
                    );
                }

                if ($retries >= self::MAX_RETRIES) {
                    break;
                }

                $sleepSeconds = self::BACKOFF_SCHEDULE_SECONDS[$retries];
                Log::warning('[MetaApiClient] rate-limit hit, applying exponential backoff', [
                    'correlation_id'     => $correlationId,
                    'endpoint'           => $url,
                    'method'             => $method,
                    'attempt'            => $retries + 1,
                    'retries_remaining'  => self::MAX_RETRIES - $retries,
                    'sleep_seconds'      => $sleepSeconds,
                    'http_status'        => $lastStatus,
                    'meta_error_subcode' => $subcode,
                ]);

                $this->sleep($sleepSeconds);
                $retries++;
            } catch (GuzzleException $e) {
                // Transport-level failure (connect/timeout/DNS) — not retried.
                throw new MetaApiException(
                    sprintf('Meta API transport error on %s %s: %s', $method, $url, $e->getMessage()),
                    0,
                    $e,
                    [
                        'endpoint'       => $url,
                        'method'         => $method,
                        'correlation_id' => $correlationId,
                    ]
                );
            }
        }

        // All 5 retries exhausted on a persistent 429/613.
        throw new MetaApiRateLimitException(
            sprintf(
                'Meta API rate limit persisted after %d retries on %s %s',
                self::MAX_RETRIES,
                $method,
                $url
            ),
            $lastStatus !== 0 ? $lastStatus : 429,
            $lastException,
            [
                'endpoint'       => $url,
                'method'         => $method,
                'http_status'    => $lastStatus,
                'response_body'  => $lastBody,
                'correlation_id' => $correlationId,
            ],
            retriesUsed: self::MAX_RETRIES,
        );
    }

    /**
     * Proactively refresh the access token when it expires within 7 days.
     *
     * `MetaTokenRefresher` is resolved through the Laravel container as a
     * forward reference, so this file is loadable even before task 7.2
     * lands its implementation. At runtime the container will throw a
     * BindingResolutionException if the class is still missing, which is
     * the right failure mode for an integration test that exercises the
     * full pipeline.
     *
     * Validates: Requirement 15.6.
     */
    private function refreshTokenIfNeeded(): void
    {
        if ($this->account->expiresWithinDays(7)) {
            app(MetaTokenRefresher::class)->refresh($this->account);
        }
    }

    /**
     * Extract the Meta-specific `error_subcode` from an error body, if any.
     *
     * Meta wraps every error in `{ "error": { ..., "error_subcode": <int> } }`.
     * Returns `null` when the body is empty, not JSON, or the field is
     * missing — callers must treat that as "not a rate-limit sub-code".
     */
    private function extractMetaSubcode(?string $body): ?int
    {
        if ($body === null || $body === '') {
            return null;
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }
        $sub = $decoded['error']['error_subcode'] ?? null;
        if (is_int($sub)) {
            return $sub;
        }
        return is_numeric($sub) ? (int) $sub : null;
    }

    /**
     * Sleep wrapper, isolated so tests can override with a fake clock and
     * assert the backoff schedule without spending wall-clock seconds
     * (see task 7.5 — "verificar backoff con reloj falso").
     */
    protected function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
