<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Meta;

use Aero\MasterAds\Classes\Exceptions\MetaApiException;
use Aero\MasterAds\Classes\Exceptions\MetaApiRateLimitException;
use Aero\MasterAds\Classes\Meta\MetaApiClient;
use Aero\MasterAds\Models\MetaAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * MetaApiClientTest — covers the HTTP transport layer wrapping Meta Graph API
 * defined in {@see MetaApiClient}.
 *
 * The whole suite runs against a synthetic Guzzle transport built on top of
 * {@see MockHandler} / {@see HandlerStack}; no real network calls are issued.
 *
 * Wall-clock backoff is replaced by an in-process subclass
 * ({@see TestableMetaApiClient}) that records every requested sleep duration
 * into an array instead of actually pausing the test process, so the
 * exponential backoff schedule (1, 2, 4, 8, 16 seconds) can be asserted
 * deterministically without slowing the suite down.
 *
 * MetaAccount instances are produced as lightweight anonymous-class stubs
 * via {@see self::makeAccountStub()} — no database row is created, allowing
 * the suite to extend {@see \PluginTestCase} with `autoMigrate = false`
 * (we only need the framework booted to satisfy `MetaApiClient`'s typed
 * dependency on a Model subclass).
 *
 * Validates: Requirements 3.6, 3.7, 14.3, 15.6.
 */
class MetaApiClientTest extends \PluginTestCase
{
    /**
     * Skip the full plugin-suite migration: none of these tests touch the
     * database. The framework still boots (so `Crypt`, container, config
     * are available) but we shave a couple of seconds off setUp.
     */
    protected $autoMigrate = false;

    /**
     * Build a MetaApiClient backed by a {@see MockHandler} pre-loaded with
     * the supplied queue of responses / exceptions.
     *
     * @param array<int, Response|RequestException|ConnectException> $queue
     */
    private function buildClient(array $queue, ?MetaAccount $account = null): array
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $http = new Client(['handler' => $stack, 'base_uri' => 'https://graph.facebook.com/v19.0/']);

        $account = $account ?? $this->makeAccountStub();
        $client = new MetaApiClient($account, $http);

        return [$client, $mock];
    }

    /**
     * Build a {@see TestableMetaApiClient} (which captures sleep calls
     * instead of really sleeping) backed by a {@see MockHandler}.
     *
     * @param array<int, Response|RequestException|ConnectException> $queue
     */
    private function buildTestableClient(array $queue, ?MetaAccount $account = null): TestableMetaApiClient
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $http = new Client(['handler' => $stack, 'base_uri' => 'https://graph.facebook.com/v19.0/']);

        $account = $account ?? $this->makeAccountStub();

        return new TestableMetaApiClient($account, $http);
    }

    /**
     * Produce a {@see MetaAccount} stub that does NOT touch the database
     * and never triggers the proactive token-refresh path inside
     * {@see MetaApiClient::refreshTokenIfNeeded()}.
     *
     * The anonymous subclass deliberately skips `parent::__construct()` to
     * avoid Eloquent boot-up; the only two collaborator surfaces the
     * client uses are overridden directly:
     *
     *   - `expiresWithinDays()` returns `false` (no refresh attempted).
     *   - `getAccessTokenAttribute()` returns a deterministic plaintext
     *     token, bypassing the Crypt mutator round-trip.
     */
    private function makeAccountStub(string $token = 'fake-access-token-abc123'): MetaAccount
    {
        return new class($token) extends MetaAccount {
            public function __construct(private readonly string $stubToken)
            {
                // Intentionally skip parent::__construct() so this stub
                // can be instantiated without booting Eloquent events.
            }

            public function expiresWithinDays(int $days): bool
            {
                return false;
            }

            public function getAccessTokenAttribute($value = null): ?string
            {
                return $this->stubToken;
            }
        };
    }

    //
    // 1. Pagination
    //

    /**
     * The generator yielded by `getPaginated()` MUST walk every page
     * surfaced by `paging.next` in order, flattening their `data[]`
     * arrays into a single iterator.
     */
    public function testGetPaginatedYieldsAllItemsAcrossPages(): void
    {
        $page1 = new Response(200, [], (string) json_encode([
            'data' => [['id' => 'A1'], ['id' => 'A2']],
            'paging' => ['next' => 'https://graph.facebook.com/v19.0/act_1/campaigns?after=p1'],
        ]));
        $page2 = new Response(200, [], (string) json_encode([
            'data' => [['id' => 'B1'], ['id' => 'B2']],
            'paging' => ['next' => 'https://graph.facebook.com/v19.0/act_1/campaigns?after=p2'],
        ]));
        $page3 = new Response(200, [], (string) json_encode([
            'data' => [['id' => 'C1']],
            // No paging.next → end of stream.
        ]));

        [$client] = $this->buildClient([$page1, $page2, $page3]);

        $items = iterator_to_array($client->getPaginated('act_1/campaigns'), false);

        $this->assertSame(
            [['id' => 'A1'], ['id' => 'A2'], ['id' => 'B1'], ['id' => 'B2'], ['id' => 'C1']],
            $items,
            'getPaginated must yield items from every page in iteration order.'
        );
    }

    /**
     * A single-page response with no `paging.next` MUST terminate the
     * generator after emitting the page's items — no further HTTP requests
     * are issued (asserted indirectly via MockHandler queue exhaustion).
     */
    public function testGetPaginatedStopsOnMissingNext(): void
    {
        $singlePage = new Response(200, [], (string) json_encode([
            'data' => [['id' => 'only-1'], ['id' => 'only-2']],
            'paging' => [], // explicitly empty
        ]));

        [$client, $mock] = $this->buildClient([$singlePage]);

        $items = iterator_to_array($client->getPaginated('act_1/campaigns'), false);

        $this->assertSame([['id' => 'only-1'], ['id' => 'only-2']], $items);
        $this->assertSame(0, $mock->count(), 'No further requests must be enqueued after the last page.');
    }

    //
    // 2. Single call — happy path
    //

    /**
     * `call()` MUST return the decoded JSON body as an associative array
     * on a 2xx response, with no retries needed.
     */
    public function testCallReturnsDecodedJson(): void
    {
        $payload = ['data' => ['id' => '123', 'name' => 'Campaign Foo']];
        [$client] = $this->buildClient([new Response(200, [], (string) json_encode($payload))]);

        $result = $client->call('GET', 'act_1/campaigns/123');

        $this->assertSame($payload, $result);
    }

    //
    // 3. Backoff on rate limits
    //

    /**
     * On a sequence of 3 × HTTP 429 followed by a 200, the client MUST
     * sleep 1, 2, 4 seconds (schedule prefix) between attempts and finally
     * return the success body. After 3 retries the client succeeds, so
     * sleepCalls === [1, 2, 4] (one sleep per retry, none after success).
     */
    public function testBackoffRetriesOn429UntilSuccess(): void
    {
        $request = new Request('GET', 'act_1/campaigns');
        $rateLimited = static function () use ($request): RequestException {
            return new RequestException(
                'Rate limited',
                $request,
                new Response(429, [], (string) json_encode(['error' => ['message' => 'rate']]))
            );
        };

        $successBody = ['data' => [['id' => 'after-retry']]];

        $client = $this->buildTestableClient([
            $rateLimited(),
            $rateLimited(),
            $rateLimited(),
            new Response(200, [], (string) json_encode($successBody)),
        ]);

        $result = $client->call('GET', 'act_1/campaigns');

        $this->assertSame($successBody, $result);
        $this->assertSame([1, 2, 4], $client->sleepCalls, 'Backoff schedule prefix must be 1, 2, 4 for 3 retries.');
    }

    /**
     * On six consecutive 429 responses (initial + 5 retries) the client
     * MUST exhaust the backoff schedule, sleep 1, 2, 4, 8, 16 seconds and
     * finally throw {@see MetaApiRateLimitException} with `retriesUsed === 5`.
     */
    public function testBackoffThrowsRateLimitExceptionAfter5Retries(): void
    {
        $request = new Request('GET', 'act_1/campaigns');
        $rateLimited = static function () use ($request): RequestException {
            return new RequestException(
                'Rate limited',
                $request,
                new Response(429, [], (string) json_encode(['error' => ['message' => 'rate']]))
            );
        };

        $client = $this->buildTestableClient([
            $rateLimited(),
            $rateLimited(),
            $rateLimited(),
            $rateLimited(),
            $rateLimited(),
            $rateLimited(),
        ]);

        try {
            $client->call('GET', 'act_1/campaigns');
            $this->fail('Expected MetaApiRateLimitException after exhausting the retry budget.');
        } catch (MetaApiRateLimitException $e) {
            $this->assertSame(5, $e->retriesUsed, 'Exception must report 5 retries used.');
            $this->assertSame(429, $e->getCode());
        }

        $this->assertSame(
            [1, 2, 4, 8, 16],
            $client->sleepCalls,
            'Backoff schedule must be the full 1, 2, 4, 8, 16 second progression.'
        );
    }

    /**
     * A 400 response whose body carries `error.error_subcode === 613`
     * (Meta application-rate-limit signal) MUST trigger the same backoff
     * loop as a 429 — i.e. a single retry then success — with one sleep
     * recorded.
     */
    public function testBackoffDetectsMetaSubcode613(): void
    {
        $request = new Request('GET', 'act_1/campaigns');
        $subcode613Response = new Response(400, [], (string) json_encode([
            'error' => [
                'message'        => 'Application request limit reached',
                'error_subcode'  => 613,
                'code'           => 17,
            ],
        ]));
        $subcode613Exception = new RequestException('App rate limit', $request, $subcode613Response);

        $successBody = ['data' => [['id' => 'after-613']]];

        $client = $this->buildTestableClient([
            $subcode613Exception,
            new Response(200, [], (string) json_encode($successBody)),
        ]);

        $result = $client->call('GET', 'act_1/campaigns');

        $this->assertSame($successBody, $result);
        $this->assertSame([1], $client->sleepCalls, 'Subcode 613 must trigger exactly one backoff before the retry.');
    }

    //
    // 4. Non-rate-limit failures
    //

    /**
     * A 500 response MUST surface as a plain {@see MetaApiException} —
     * NOT the rate-limit subclass — and the backoff loop MUST NOT engage
     * (no sleep call recorded).
     */
    public function testNonRateLimitErrorThrowsMetaApiExceptionWithoutRetry(): void
    {
        $request = new Request('GET', 'act_1/campaigns');
        $serverError = new RequestException(
            'Server error',
            $request,
            new Response(500, [], (string) json_encode(['error' => ['message' => 'boom']]))
        );

        $client = $this->buildTestableClient([$serverError]);

        try {
            $client->call('GET', 'act_1/campaigns');
            $this->fail('Expected MetaApiException for a 500 response.');
        } catch (MetaApiRateLimitException $e) {
            $this->fail('500 responses must NOT be classified as rate-limit failures.');
        } catch (MetaApiException $e) {
            $this->assertSame(500, $e->getCode());
            $this->assertSame(500, $e->context['http_status'] ?? null);
        }

        $this->assertSame([], $client->sleepCalls, 'Non-rate-limit failures must not engage the backoff schedule.');
    }

    /**
     * A transport-level failure (e.g. DNS / connection refused) MUST be
     * wrapped in {@see MetaApiException} with code 0 — never propagated as
     * the underlying Guzzle exception type, and never retried.
     */
    public function testTransportErrorThrowsMetaApiException(): void
    {
        $request = new Request('GET', 'act_1/campaigns');
        $connectError = new ConnectException('Connection refused: graph.facebook.com', $request);

        $client = $this->buildTestableClient([$connectError]);

        try {
            $client->call('GET', 'act_1/campaigns');
            $this->fail('Expected MetaApiException for a transport-level failure.');
        } catch (MetaApiRateLimitException $e) {
            $this->fail('Transport errors must NOT be classified as rate-limit failures.');
        } catch (MetaApiException $e) {
            $this->assertSame(0, $e->getCode(), 'Transport errors must carry exception code 0.');
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }

        $this->assertSame([], $client->sleepCalls, 'Transport errors must not engage the backoff schedule.');
    }
}

/**
 * TestableMetaApiClient — subclass that records every requested
 * `sleep($seconds)` instead of actually pausing the process, so the
 * exponential backoff schedule can be asserted deterministically.
 *
 * Used exclusively by {@see MetaApiClientTest}; kept in the same file to
 * make the override visible at the point of use and avoid creating a
 * dedicated fixtures namespace.
 */
class TestableMetaApiClient extends MetaApiClient
{
    /**
     * Ordered list of every `sleep($seconds)` call the client requested
     * during the lifetime of this instance. The test asserts on this
     * array directly.
     *
     * @var array<int, int>
     */
    public array $sleepCalls = [];

    /**
     * Override the wall-clock sleep with a recording-only no-op.
     */
    protected function sleep(int $seconds): void
    {
        $this->sleepCalls[] = $seconds;
    }
}
