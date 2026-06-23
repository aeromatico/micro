<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Ai;

use Aero\MasterAds\Classes\Ai\AiResponse;
use Aero\MasterAds\Classes\Ai\OpenRouterClient;
use Aero\MasterAds\Classes\Exceptions\AiProviderException;
use Aero\MasterAds\Models\AiProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * OpenRouterClientTest — covers the HTTP transport, response parsing,
 * cost estimation and authentication header wiring of
 * {@see OpenRouterClient}.
 *
 * The whole suite runs against a synthetic Guzzle transport built on top
 * of {@see MockHandler} / {@see HandlerStack}; no real network calls are
 * issued. AiProvider instances are produced as lightweight anonymous-class
 * stubs via {@see self::makeProviderStub()} — no database row is created
 * and the encryption mutators are bypassed entirely.
 *
 * The suite extends `\PHPUnit\Framework\TestCase` directly: the class
 * under test is a pure HTTP client with no Eloquent / facade dependencies
 * once the AiProvider stub is supplied.
 *
 * Validates: Requirements 5.5, 6.5, 6.6, 16.2
 */
class OpenRouterClientTest extends TestCase
{
    /**
     * Produce an {@see AiProvider} stub that does NOT touch the database
     * and bypasses the `Crypt` mutator round-trip on `api_key`.
     *
     * The anonymous subclass deliberately skips `parent::__construct()`
     * so it can be instantiated without booting Eloquent events; the
     * three attribute surfaces consumed by {@see OpenRouterClient}
     * (`model`, `api_key`, `driver`, `settings`) are overridden by
     * intercepting `getAttribute()` / `__get()`.
     *
     * @param array<string,mixed> $settings
     */
    private function makeProviderStub(string $model = 'anthropic/claude-3.5-sonnet', array $settings = []): AiProvider
    {
        return new class($model, $settings) extends AiProvider {
            public function __construct(private string $stubModel, private array $stubSettings)
            {
                // Skip parent::__construct() to avoid booting Eloquent.
            }

            public function getAttribute($key)
            {
                return match ($key) {
                    'model'    => $this->stubModel,
                    'api_key'  => 'sk-stub',
                    'driver'   => 'openrouter',
                    'settings' => $this->stubSettings,
                    default    => null,
                };
            }

            public function __get($key)
            {
                return $this->getAttribute($key);
            }
        };
    }

    /**
     * Build a Guzzle client whose request pipeline taps every outgoing
     * call into the caller-supplied reference so the test can assert
     * headers and serialised payloads.
     *
     * The MockHandler is pre-loaded with a benign 200 response so
     * {@see OpenRouterClient::complete()} can complete its happy-path
     * pipeline; tests that need to assert on the response body inject
     * their own response queue instead.
     *
     * @return array{0: Client, 1: MockHandler}
     */
    private function captureRequest(&$captured): array
    {
        $mock = new MockHandler([
            new Response(
                200,
                [],
                '{"choices":[{"message":{"content":"{}"}}],"usage":{"prompt_tokens":1,"completion_tokens":1}}'
            ),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(function ($req) use (&$captured): void {
            $captured = $req;
        }));

        return [new Client(['handler' => $stack]), $mock];
    }

    /**
     * Helper to build a client+http pair from a MockHandler queue.
     *
     * @param array<int, Response|\Throwable> $queue
     * @return array{0: OpenRouterClient, 1: MockHandler}
     */
    private function buildClient(array $queue, ?AiProvider $provider = null): array
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $http = new Client(['handler' => $stack]);

        $client = new OpenRouterClient($provider ?? $this->makeProviderStub(), $http);

        return [$client, $mock];
    }

    //
    // 1. Cost estimation
    //

    /**
     * Claude 3.5 Sonnet is listed in the static pricing table at
     * $3/M prompt + $15/M completion tokens. 1M of each MUST therefore
     * yield exactly $18.00 (Requirement 16.2).
     */
    public function testEstimateCostForKnownModel(): void
    {
        $client = new OpenRouterClient(
            $this->makeProviderStub('anthropic/claude-3.5-sonnet'),
            new Client(['handler' => HandlerStack::create(new MockHandler())])
        );

        $cost = $client->estimateCost(1_000_000, 1_000_000);

        $this->assertSame(18.00, $cost);
    }

    /**
     * An unknown model MUST fall back to the conservative default
     * pricing of $1/M prompt + $5/M completion tokens — never zero —
     * so `Ai_Analysis.cost_usd` is always meaningful (Requirement 16.2).
     */
    public function testEstimateCostForUnknownModel(): void
    {
        $client = new OpenRouterClient(
            $this->makeProviderStub('unknown/foo'),
            new Client(['handler' => HandlerStack::create(new MockHandler())])
        );

        $cost = $client->estimateCost(1_000_000, 1_000_000);

        // 1M * $1 + 1M * $5 = $6.00
        $this->assertSame(6.00, $cost);
    }

    //
    // 2. Happy path
    //

    /**
     * A well-formed 200 response with the canonical OpenAI-compatible
     * envelope MUST be materialised into an {@see AiResponse} whose
     * fields mirror the upstream payload, including the populated
     * `parsed` array, token counters and computed cost.
     */
    public function testCompleteReturnsAiResponseOnSuccess(): void
    {
        $body = '{"choices":[{"message":{"content":"{\"recommendations\":[]}"}}],'
             . '"usage":{"prompt_tokens":100,"completion_tokens":50}}';

        [$client] = $this->buildClient([new Response(200, [], $body)]);

        $response = $client->complete('sys prompt', 'user prompt', ['json_schema' => ['x' => 'y']]);

        $this->assertInstanceOf(AiResponse::class, $response);
        $this->assertSame('{"recommendations":[]}', $response->raw);
        $this->assertSame(['recommendations' => []], $response->parsed);
        $this->assertSame(100, $response->promptTokens);
        $this->assertSame(50, $response->completionTokens);
        $this->assertSame('anthropic/claude-3.5-sonnet', $response->model);
        // 100 * 3/1M + 50 * 15/1M = 0.0003 + 0.00075 = 0.001050
        $this->assertGreaterThan(0.0, $response->costUsd);
    }

    /**
     * Some models occasionally wrap their JSON output in a ```json …
     * ``` markdown fence despite `response_format = json_object`. The
     * client MUST transparently strip the fence and parse the embedded
     * JSON (Requirement 6.5).
     */
    public function testCompleteStripsMarkdownFences(): void
    {
        $fenced = "```json\n{\"recommendations\":[]}\n```";
        $body = json_encode([
            'choices' => [['message' => ['content' => $fenced]]],
            'usage'   => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ]);

        [$client] = $this->buildClient([new Response(200, [], (string) $body)]);

        $response = $client->complete('sys', 'usr');

        $this->assertSame(['recommendations' => []], $response->parsed);
    }

    //
    // 3. Error paths
    //

    /**
     * A 5xx response from the upstream MUST be wrapped into an
     * {@see AiProviderException} so the Recommendation_Engine can mark
     * the {@code Ai_Analysis} as `failed` (Requirement 6.9).
     */
    public function testCompleteThrowsOnNon2xx(): void
    {
        [$client] = $this->buildClient([new Response(500, [], '{"error":"boom"}')]);

        $this->expectException(AiProviderException::class);

        $client->complete('sys', 'usr');
    }

    /**
     * A 200 response carrying a non-JSON body MUST also surface as an
     * {@see AiProviderException} — the engine never silently accepts a
     * malformed envelope.
     */
    public function testCompleteThrowsOnMalformedJson(): void
    {
        [$client] = $this->buildClient([new Response(200, [], 'not json')]);

        $this->expectException(AiProviderException::class);

        $client->complete('sys', 'usr');
    }

    /**
     * A 200 response whose body decodes successfully but omits the
     * canonical `choices[0].message.content` path MUST surface as an
     * {@see AiProviderException} so the caller never dereferences a
     * missing field.
     */
    public function testCompleteThrowsWhenChoicesMissing(): void
    {
        [$client] = $this->buildClient([new Response(200, [], '{"foo":"bar"}')]);

        $this->expectException(AiProviderException::class);

        $client->complete('sys', 'usr');
    }

    //
    // 4. Headers / payload wiring
    //

    /**
     * The outgoing request MUST carry the `Authorization: Bearer <key>`
     * header derived from `AiProvider.api_key` so OpenRouter can
     * authenticate the call (Requirement 5.5).
     */
    public function testCompleteIncludesAuthHeader(): void
    {
        $captured = null;
        [$http] = $this->captureRequest($captured);

        $client = new OpenRouterClient($this->makeProviderStub(), $http);
        $client->complete('sys', 'usr');

        $this->assertNotNull($captured, 'Tap middleware must capture the outgoing request.');
        $this->assertSame(
            'Bearer sk-stub',
            $captured->getHeaderLine('Authorization'),
            'Authorization header must carry the bearer token from AiProvider.api_key.'
        );
    }

    /**
     * When the caller supplies a `json_schema` option, the request
     * payload MUST set `response_format.type = "json_object"` so
     * OpenRouter coerces Claude / GPT-4o into emitting parseable JSON
     * (Requirement 6.5).
     */
    public function testCompleteUsesResponseFormatWhenJsonSchemaProvided(): void
    {
        $captured = null;
        [$http] = $this->captureRequest($captured);

        $client = new OpenRouterClient($this->makeProviderStub(), $http);
        $client->complete('sys', 'usr', ['json_schema' => ['some' => 'schema']]);

        $this->assertNotNull($captured, 'Tap middleware must capture the outgoing request.');
        $this->assertSame(
            'application/json',
            $captured->getHeaderLine('Content-Type'),
            'JSON requests must carry an application/json Content-Type header.'
        );

        $body = json_decode((string) $captured->getBody(), true);

        $this->assertIsArray($body, 'Request body must be valid JSON.');
        $this->assertArrayHasKey(
            'response_format',
            $body,
            'response_format must be populated when json_schema is supplied.'
        );
        $this->assertSame(
            'json_object',
            $body['response_format']['type'] ?? null,
            'response_format.type must be "json_object" when json_schema is supplied.'
        );
    }
}
