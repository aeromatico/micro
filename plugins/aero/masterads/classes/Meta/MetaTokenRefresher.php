<?php

declare(strict_types=1);

namespace Aero\MasterAds\Classes\Meta;

use Aero\MasterAds\Classes\Exceptions\MetaOAuthException;
use Aero\MasterAds\Models\MetaAccount;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MetaTokenRefresher
 *
 * Rotates a Meta long-lived access token by exchanging the current token for a
 * fresh one against Meta's Graph API `oauth/access_token` endpoint with the
 * `fb_exchange_token` grant type.
 *
 * Wire diagram of a successful refresh:
 *
 *     GET https://graph.facebook.com/{apiVersion}/oauth/access_token
 *       ?grant_type=fb_exchange_token
 *       &client_id=<META_APP_ID>
 *       &client_secret=<META_APP_SECRET>
 *       &fb_exchange_token=<current access_token>
 *
 *     200 OK
 *     { "access_token": "...", "token_type": "bearer", "expires_in": 5184000 }
 *
 * On success, the new token is persisted through MetaAccount mutators (which
 * transparently encrypt it via `Crypt::encrypt`) and `expires_at` is set to
 * `now()->addSeconds(expires_in)`. Per Requirement 2.7, the postcondition
 * `expires_at > now() + 30 days` is enforced — otherwise the refresh is
 * considered failed and a `MetaOAuthException` is thrown.
 *
 * On any failure (transport error, non-2xx response, malformed JSON, empty
 * `access_token`, or `expires_in` too short to satisfy the 30-day postcondition),
 * the refresher:
 *   1. writes a diagnostic message into `MetaAccount.last_error`,
 *   2. dispatches the event `aero.masterads.meta_token_refresh_failed` with
 *      the offending `MetaAccount` so the Workspace_Owner can be notified
 *      (Requirement 15.6),
 *   3. rethrows the failure as `MetaOAuthException` with a structured
 *      `$context` array for log correlation.
 *
 * Secrets handling: `META_APP_ID`, `META_APP_SECRET` and `META_GRAPH_API_VERSION`
 * are read from `config/services.php` (`services.master_ads_meta.*`), never
 * hardcoded — Requirement 15.4.
 *
 * Validates: Requirements 2.7, 15.6
 */
class MetaTokenRefresher
{
    /**
     * Default Graph API host. Encoded as base_uri so Guzzle can resolve the
     * relative `{apiVersion}/oauth/access_token` path.
     */
    private const GRAPH_BASE_URI = 'https://graph.facebook.com/';

    /**
     * Default HTTP timeout (seconds) for the token refresh request.
     */
    private const HTTP_TIMEOUT_SECONDS = 15;

    /**
     * Postcondition window (in days). After a successful refresh the token's
     * new `expires_at` MUST sit strictly beyond `now() + 30 days` — see
     * Requirement 2.7.
     */
    private const MIN_LIFETIME_DAYS = 30;

    /**
     * @param Client|null $http       Optional Guzzle client (injected in tests).
     *                                 When null, a default client is constructed
     *                                 lazily inside `refresh()` with the Graph
     *                                 base URI and a 15-second timeout.
     * @param string      $apiVersion Fallback Graph API version when no
     *                                 `services.master_ads_meta.api_version`
     *                                 config entry is present.
     */
    public function __construct(
        private readonly ?Client $http = null,
        private readonly string $apiVersion = 'v19.0'
    ) {
    }

    /**
     * Extends a Meta long-lived access token.
     *
     * Calls `GET {apiVersion}/oauth/access_token?grant_type=fb_exchange_token`
     * passing the current token as `fb_exchange_token` along with the Meta
     * app credentials. On success persists the new token (encrypted via the
     * `MetaAccount` mutator) and updates `expires_at`.
     *
     * Postcondition (Requirement 2.7): `account.expires_at > now() + 30 days`.
     *
     * On failure: sets `account.last_error`, dispatches the event
     * `aero.masterads.meta_token_refresh_failed` (Requirement 15.6) and
     * rethrows `MetaOAuthException`.
     *
     * @throws MetaOAuthException When the refresh cannot be completed for any
     *                            reason — including transport errors, non-2xx
     *                            HTTP responses, malformed JSON, missing
     *                            `access_token`, or a `expires_in` value too
     *                            short to satisfy the 30-day postcondition.
     */
    public function refresh(MetaAccount $account): void
    {
        $currentToken = $account->access_token;

        if ($currentToken === null || $currentToken === '') {
            $this->fail(
                $account,
                'Cannot refresh Meta token: account has no access_token to exchange.',
                ['meta_account_id' => $account->id],
            );
        }

        $appId = (string) Config::get('services.master_ads_meta.app_id', '');
        $appSecret = (string) Config::get('services.master_ads_meta.app_secret', '');

        if ($appId === '' || $appSecret === '') {
            $this->fail(
                $account,
                'Meta app credentials are not configured (services.master_ads_meta.app_id/app_secret).',
                ['meta_account_id' => $account->id],
            );
        }

        $apiVersion = (string) Config::get(
            'services.master_ads_meta.api_version',
            $this->apiVersion !== '' ? $this->apiVersion : 'v19.0'
        );

        $endpoint = sprintf('%s/oauth/access_token', ltrim($apiVersion, '/'));

        try {
            $response = $this->client()->request('GET', $endpoint, [
                'query' => [
                    'grant_type'        => 'fb_exchange_token',
                    'client_id'         => $appId,
                    'client_secret'     => $appSecret,
                    'fb_exchange_token' => $currentToken,
                ],
                'http_errors' => true,
            ]);
        } catch (GuzzleException $e) {
            $this->fail(
                $account,
                'HTTP request to Meta token refresh endpoint failed: ' . $e->getMessage(),
                [
                    'meta_account_id' => $account->id,
                    'endpoint'        => $endpoint,
                    'http_status'     => method_exists($e, 'getCode') ? $e->getCode() : 0,
                ],
                $e,
            );
        }

        $body = (string) $response->getBody();
        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            $this->fail(
                $account,
                'Meta token refresh endpoint returned a malformed JSON body.',
                [
                    'meta_account_id' => $account->id,
                    'endpoint'        => $endpoint,
                    'http_status'     => $response->getStatusCode(),
                ],
            );
        }

        if (isset($payload['error'])) {
            $error = is_array($payload['error']) ? $payload['error'] : ['message' => (string) $payload['error']];

            $this->fail(
                $account,
                'Meta rejected the token refresh: ' . ($error['message'] ?? 'unknown error'),
                [
                    'meta_account_id'    => $account->id,
                    'endpoint'           => $endpoint,
                    'http_status'        => $response->getStatusCode(),
                    'meta_error_code'    => $error['code'] ?? null,
                    'meta_error_subcode' => $error['error_subcode'] ?? null,
                    'meta_error_type'    => $error['type'] ?? null,
                ],
            );
        }

        $newToken = isset($payload['access_token']) ? (string) $payload['access_token'] : '';
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : 0;

        if ($newToken === '') {
            $this->fail(
                $account,
                'Meta token refresh response did not include an access_token.',
                [
                    'meta_account_id' => $account->id,
                    'endpoint'        => $endpoint,
                    'http_status'     => $response->getStatusCode(),
                ],
            );
        }

        $newExpiresAt = Carbon::now()->addSeconds(max(0, $expiresIn));

        // Postcondition (Requirement 2.7): refreshed token must outlive now()+30d.
        if ($newExpiresAt->lessThanOrEqualTo(Carbon::now()->addDays(self::MIN_LIFETIME_DAYS))) {
            $this->fail(
                $account,
                sprintf(
                    'Meta returned a token whose lifetime (%d seconds) does not satisfy the >30-day postcondition.',
                    $expiresIn
                ),
                [
                    'meta_account_id' => $account->id,
                    'endpoint'        => $endpoint,
                    'expires_in'      => $expiresIn,
                ],
            );
        }

        $account->access_token = $newToken;
        $account->expires_at = $newExpiresAt;
        $account->last_error = null;
        $account->save();
    }

    /**
     * Lazily build the default Guzzle client when none was injected.
     */
    private function client(): Client
    {
        if ($this->http !== null) {
            return $this->http;
        }

        return new Client([
            'base_uri' => self::GRAPH_BASE_URI,
            'timeout'  => self::HTTP_TIMEOUT_SECONDS,
        ]);
    }

    /**
     * Persist the failure on the account, fire the event and throw.
     *
     * @param  array<string,mixed> $context
     * @return never
     *
     * @throws MetaOAuthException
     */
    private function fail(
        MetaAccount $account,
        string $message,
        array $context = [],
        ?Throwable $previous = null,
    ): void {
        try {
            $account->last_error = $message;
            $account->save();
        } catch (Throwable $persistError) {
            // Persisting the diagnostic must never mask the original failure.
            Log::warning('[MasterAds] Failed to persist Meta token refresh error on account.', [
                'meta_account_id' => $account->id,
                'persist_error'   => $persistError->getMessage(),
                'original_error'  => $message,
            ]);
        }

        Event::dispatch('aero.masterads.meta_token_refresh_failed', [$account]);

        $code = $previous !== null ? (int) $previous->getCode() : 0;

        throw new MetaOAuthException($message, $code, $previous, $context);
    }
}
