<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Meta;

use Aero\MasterAds\Classes\Exceptions\MetaOAuthException;
use Aero\MasterAds\Models\MetaAccount;
use Aero\MasterAds\Models\Workspace;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MetaOAuthService — Authorization-code flow for connecting a Meta ad account.
 *
 * Drives the OAuth callback handler (`routes.php` →
 * `/aero/masterads/oauth/meta/callback`) by exchanging the temporary `code`
 * issued by Meta for a long-lived access token, fetching the connected ad
 * account, and persisting a `MetaAccount` row scoped to the active
 * `Workspace`.
 *
 * Atomicity:
 *   The entire exchange (HTTP calls + persistence) is wrapped in a single
 *   `DB::transaction(...)`. Any failure anywhere in the pipeline — Workspace
 *   load, short-lived exchange, long-lived exchange, ad account fetch, model
 *   save — triggers a rollback before the exception bubbles up as
 *   `MetaOAuthException`. No orphan rows can survive a partial flow
 *   (Requirement 2.5, Requirement 15.5).
 *
 * Idempotency:
 *   The persistence step uses `MetaAccount::updateOrCreate` keyed on
 *   `(workspace_id, meta_act_id)`. Re-running the OAuth flow for an
 *   already-connected account rotates the stored token in place rather than
 *   creating a duplicate row (Requirement 2.6).
 *
 * Encryption:
 *   The access token is handed to `MetaAccount` as plain text; the model's
 *   `setAccessTokenAttribute` mutator transparently runs it through
 *   `Crypt::encrypt` before write (Requirement 2.2). This service never
 *   stores ciphertext directly and never logs the token (Requirement 11.1
 *   / 15.5).
 *
 * Events:
 *   On success, dispatches `aero.masterads.meta_account_connected` with the
 *   persisted `MetaAccount` (Requirement 2.8). Listeners — e.g. the queued
 *   `SyncMetaAccountJob` — pick up the event from the listener registry in
 *   `Plugin.php`.
 *
 * Secrets:
 *   `app_id`, `app_secret`, `redirect` and `api_version` are read
 *   exclusively from `config('services.master_ads_meta.*')`, which is
 *   itself populated from environment variables — never hardcoded here
 *   (Requirement 15.5).
 *
 * Validates: Requirements 2.1, 2.2, 2.5, 2.6, 2.8, 2.9, 11.1, 15.5
 */
final class MetaOAuthService
{
    /**
     * Default Graph API host. Resolved against `{apiVersion}/oauth/...`
     * relative paths by the Guzzle client.
     */
    private const GRAPH_BASE_URI = 'https://graph.facebook.com/';

    /**
     * Default HTTP timeout (seconds) for every OAuth request. Kept tight on
     * purpose: the OAuth callback runs synchronously inside the user's
     * browser redirect, so we cannot afford long stalls.
     */
    private const HTTP_TIMEOUT_SECONDS = 15;

    /**
     * Fallback long-lived token lifetime when Meta omits `expires_in`. Meta
     * documents long-lived user tokens at ~60 days = 5_184_000 seconds.
     */
    private const DEFAULT_LONG_LIVED_TTL_SECONDS = 5_184_000;

    /**
     * @param Client|null $http Optional Guzzle client (overridable in tests
     *                          via DI for fake/mock transports). When null,
     *                          a default client is built lazily inside
     *                          `exchangeCode()` with the Graph base URI and
     *                          a 15-second per-request timeout.
     */
    public function __construct(
        private readonly ?Client $http = null,
    ) {
    }

    /**
     * Exchange an OAuth `code` for a long-lived access token and persist a
     * `MetaAccount` row for the supplied workspace.
     *
     * High-level pipeline (every step happens inside one DB transaction):
     *
     *   1. Validate `$code` and load the target `Workspace`.
     *   2. Read Meta app credentials from `config/services.php`.
     *   3. `GET {apiVersion}/oauth/access_token` exchanging `code` for a
     *      short-lived token.
     *   4. `GET {apiVersion}/oauth/access_token` with
     *      `grant_type=fb_exchange_token` to upgrade the short-lived token
     *      to a ~60-day long-lived one.
     *   5. `GET {apiVersion}/me/adaccounts?fields=...` and select the first
     *      ad account returned (single-account MVP — see TODO below).
     *   6. `MetaAccount::updateOrCreate` keyed on
     *      `(workspace_id, meta_act_id)` — encrypts the token via the
     *      model's mutator.
     *   7. Dispatch `aero.masterads.meta_account_connected` with the row.
     *
     * Atomic (`DB::transaction`): any failure rolls back BEFORE re-throwing
     * as `MetaOAuthException`, so the database is left exactly as it was
     * before the call (Requirements 2.5, 15.5).
     *
     * Idempotent on `(workspace_id, meta_act_id)`: re-running the OAuth
     * flow for an already-connected account rotates the stored token in
     * place rather than inserting a duplicate (Requirement 2.6).
     *
     * The `?error=...` branch from Meta is handled upstream in the
     * callback controller — by contract this method is invoked only when
     * a non-empty `code` is present (Requirement 2.9).
     *
     * @param  string $code         Authorization code returned by Meta on
     *                              its OAuth redirect.
     * @param  int    $workspaceId  ID of the `Workspace` the new account
     *                              should be scoped to.
     * @return MetaAccount          Persisted (or updated) account row.
     *
     * @throws MetaOAuthException   On any failure — invalid input, missing
     *                              config, transport/HTTP error, malformed
     *                              Meta response, or database error.
     *
     * Validates: Requirements 2.1, 2.2, 2.5, 2.6, 2.8, 2.9, 11.1, 15.5
     */
    public function exchangeCode(string $code, int $workspaceId): MetaAccount
    {
        if ($code === '') {
            throw new MetaOAuthException(
                'OAuth code is empty',
                0,
                null,
                ['workspace_id' => $workspaceId, 'step' => 'validate_input'],
            );
        }

        try {
            $workspace = Workspace::findOrFail($workspaceId);
        } catch (ModelNotFoundException $e) {
            throw new MetaOAuthException(
                sprintf('Workspace %d not found for OAuth exchange', $workspaceId),
                0,
                $e,
                ['workspace_id' => $workspaceId, 'step' => 'load_workspace'],
            );
        }

        $appId      = (string) Config::get('services.master_ads_meta.app_id', '');
        $appSecret  = (string) Config::get('services.master_ads_meta.app_secret', '');
        $redirect   = (string) Config::get('services.master_ads_meta.redirect', '');
        $apiVersion = (string) Config::get('services.master_ads_meta.api_version', 'v19.0');

        if ($appId === '' || $appSecret === '' || $redirect === '' || $apiVersion === '') {
            throw new MetaOAuthException(
                'Meta OAuth credentials are not fully configured '
                . '(services.master_ads_meta.app_id / app_secret / redirect / api_version).',
                0,
                null,
                [
                    'workspace_id' => $workspaceId,
                    'step'         => 'load_config',
                    // Token-free presence flags only — never log secret values (Requirement 11.1).
                    'has_app_id'      => $appId !== '',
                    'has_app_secret'  => $appSecret !== '',
                    'has_redirect'    => $redirect !== '',
                    'has_api_version' => $apiVersion !== '',
                ],
            );
        }

        $http = $this->http ?? new Client([
            'base_uri' => self::GRAPH_BASE_URI,
            'timeout'  => self::HTTP_TIMEOUT_SECONDS,
        ]);

        /** @var MetaAccount $metaAccount */
        $metaAccount = DB::transaction(function () use (
            $http,
            $code,
            $workspaceId,
            $appId,
            $appSecret,
            $redirect,
            $apiVersion
        ): MetaAccount {
            try {
                // ── Step 1: code → short-lived token ───────────────────────────
                $shortLived = $this->fetchShortLivedToken(
                    $http,
                    $apiVersion,
                    $appId,
                    $appSecret,
                    $redirect,
                    $code,
                );

                // ── Step 2: short-lived → long-lived token ────────────────────
                [$longLived, $expiresIn] = $this->fetchLongLivedToken(
                    $http,
                    $apiVersion,
                    $appId,
                    $appSecret,
                    $shortLived,
                );

                // ── Step 3: pick the first ad account ─────────────────────────
                // TODO(phase-2, multi-account): present the full `data[]` list
                // to the user and let them pick which ad account to connect
                // (or connect all of them in a single workspace). The MVP
                // assumes a single-account flow.
                $adAccount = $this->fetchPrimaryAdAccount($http, $apiVersion, $longLived);

                $expiresAt = Carbon::now()->addSeconds(
                    $expiresIn > 0 ? $expiresIn : self::DEFAULT_LONG_LIVED_TTL_SECONDS
                );

                // ── Step 4: persist (idempotent on (workspace_id, meta_act_id)) ──
                $metaAccount = MetaAccount::updateOrCreate(
                    [
                        'workspace_id' => $workspaceId,
                        'meta_act_id'  => (string) $adAccount['id'],
                    ],
                    [
                        'name'         => $adAccount['name'] ?? null,
                        'currency'     => $adAccount['currency'] ?? 'USD',
                        // Encrypted transparently by MetaAccount::setAccessTokenAttribute
                        // (Requirement 2.2) — token never leaves this scope in plain text.
                        'access_token' => $longLived,
                        'expires_at'   => $expiresAt,
                        'last_error'   => null,
                    ],
                );

                // ── Step 5: notify the rest of the system ─────────────────────
                Event::dispatch('aero.masterads.meta_account_connected', [$metaAccount]);

                // Token-free success log (Requirement 11.1 / 15.5).
                Log::info('[MasterAds] Meta account connected via OAuth.', [
                    'workspace_id'    => $workspaceId,
                    'meta_account_id' => $metaAccount->id,
                    'meta_act_id'     => $metaAccount->meta_act_id,
                    'expires_at'      => $expiresAt->toIso8601String(),
                ]);

                return $metaAccount;
            } catch (MetaOAuthException $e) {
                // Already shaped — let DB::transaction roll back and propagate.
                throw $e;
            } catch (Throwable $e) {
                // Any other failure (DB, validation, unforeseen) — wrap it so
                // the caller only ever sees MetaOAuthException, and the
                // transaction rolls back automatically.
                throw new MetaOAuthException(
                    'Unexpected failure during Meta OAuth exchange: ' . $e->getMessage(),
                    (int) $e->getCode(),
                    $e,
                    [
                        'workspace_id' => $workspaceId,
                        'step'         => 'transaction_body',
                    ],
                );
            }
        });

        return $metaAccount;
    }

    /**
     * Step 1 — exchange the authorization `code` for a short-lived
     * (~1-hour) user access token.
     *
     * @throws MetaOAuthException When the call fails or the response does
     *                            not contain an `access_token`.
     */
    private function fetchShortLivedToken(
        Client $http,
        string $apiVersion,
        string $appId,
        string $appSecret,
        string $redirect,
        string $code,
    ): string {
        $payload = $this->getJson(
            $http,
            sprintf('%s/oauth/access_token', ltrim($apiVersion, '/')),
            [
                'client_id'     => $appId,
                'client_secret' => $appSecret,
                'redirect_uri'  => $redirect,
                'code'          => $code,
            ],
            step: 'exchange_short_lived',
        );

        $token = isset($payload['access_token']) ? (string) $payload['access_token'] : '';
        if ($token === '') {
            throw new MetaOAuthException(
                'Meta short-lived token response did not contain an access_token.',
                0,
                null,
                ['step' => 'exchange_short_lived'],
            );
        }
        return $token;
    }

    /**
     * Step 2 — upgrade a short-lived user token to a long-lived (~60-day)
     * one via the `fb_exchange_token` grant.
     *
     * @return array{0:string, 1:int}  Tuple of `[longLivedToken, expiresInSeconds]`.
     *                                 `expiresInSeconds` is 0 when Meta
     *                                 omits the field (caller falls back
     *                                 to {@see self::DEFAULT_LONG_LIVED_TTL_SECONDS}).
     *
     * @throws MetaOAuthException When the call fails or the response does
     *                            not contain an `access_token`.
     */
    private function fetchLongLivedToken(
        Client $http,
        string $apiVersion,
        string $appId,
        string $appSecret,
        string $shortLivedToken,
    ): array {
        $payload = $this->getJson(
            $http,
            sprintf('%s/oauth/access_token', ltrim($apiVersion, '/')),
            [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $appSecret,
                'fb_exchange_token' => $shortLivedToken,
            ],
            step: 'exchange_long_lived',
        );

        $token = isset($payload['access_token']) ? (string) $payload['access_token'] : '';
        if ($token === '') {
            throw new MetaOAuthException(
                'Meta long-lived token response did not contain an access_token.',
                0,
                null,
                ['step' => 'exchange_long_lived'],
            );
        }

        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : 0;

        return [$token, $expiresIn];
    }

    /**
     * Step 3 — fetch the connected ad accounts and return the first one.
     *
     * The MVP single-account flow picks `data[0]`. A phase-2 enhancement
     * will instead present `data[]` to the user as a selection screen.
     *
     * @return array{id:string, account_id?:string, name?:string, currency?:string, account_status?:int}
     *
     * @throws MetaOAuthException When the call fails or the account list
     *                            is empty / malformed.
     */
    private function fetchPrimaryAdAccount(
        Client $http,
        string $apiVersion,
        string $longLivedToken,
    ): array {
        $payload = $this->getJson(
            $http,
            sprintf('%s/me/adaccounts', ltrim($apiVersion, '/')),
            [
                'fields'       => 'id,account_id,name,currency,account_status',
                'access_token' => $longLivedToken,
            ],
            step: 'fetch_adaccounts',
        );

        $data = $payload['data'] ?? null;
        if (!is_array($data) || count($data) === 0) {
            throw new MetaOAuthException(
                'Meta /me/adaccounts response did not contain any ad account.',
                0,
                null,
                ['step' => 'fetch_adaccounts'],
            );
        }

        $first = $data[0];
        if (!is_array($first) || empty($first['id'])) {
            throw new MetaOAuthException(
                'Meta /me/adaccounts first entry is malformed (missing id).',
                0,
                null,
                ['step' => 'fetch_adaccounts'],
            );
        }

        return $first;
    }

    /**
     * Issue a single `GET` request and decode the JSON body, surfacing every
     * failure mode (transport error, non-2xx status, malformed JSON,
     * Meta-payload `error` field) as a `MetaOAuthException` tagged with the
     * pipeline step for log correlation.
     *
     * @param  array<string,string|int> $query
     * @return array<string,mixed>      Decoded JSON body.
     *
     * @throws MetaOAuthException
     */
    private function getJson(Client $http, string $endpoint, array $query, string $step): array
    {
        try {
            $response = $http->request('GET', $endpoint, [
                'query'       => $query,
                'http_errors' => true,
            ]);
        } catch (GuzzleException $e) {
            throw new MetaOAuthException(
                sprintf('Meta OAuth HTTP call failed at step "%s": %s', $step, $e->getMessage()),
                (int) $e->getCode(),
                $e,
                ['step' => $step, 'endpoint' => $endpoint],
            );
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new MetaOAuthException(
                sprintf('Meta OAuth call returned HTTP %d at step "%s".', $status, $step),
                $status,
                null,
                ['step' => $step, 'endpoint' => $endpoint, 'http_status' => $status],
            );
        }

        $body    = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new MetaOAuthException(
                sprintf('Meta OAuth call returned malformed JSON at step "%s".', $step),
                $status,
                null,
                ['step' => $step, 'endpoint' => $endpoint, 'http_status' => $status],
            );
        }

        if (isset($decoded['error'])) {
            $error = is_array($decoded['error']) ? $decoded['error'] : ['message' => (string) $decoded['error']];
            throw new MetaOAuthException(
                sprintf(
                    'Meta rejected OAuth call at step "%s": %s',
                    $step,
                    $error['message'] ?? 'unknown error'
                ),
                $status,
                null,
                [
                    'step'               => $step,
                    'endpoint'           => $endpoint,
                    'http_status'        => $status,
                    'meta_error_code'    => $error['code']          ?? null,
                    'meta_error_subcode' => $error['error_subcode'] ?? null,
                    'meta_error_type'    => $error['type']          ?? null,
                ],
            );
        }

        return $decoded;
    }
}
