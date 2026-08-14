<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Ai;

use Aero\MasterAds\Classes\Ai\AiResponse;
use Aero\MasterAds\Classes\Ai\PromptBuilder;
use Aero\MasterAds\Classes\Ai\ResponseParser;
use Aero\MasterAds\Classes\Exceptions\AiProviderException;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * ResponseParserTest — verifies that {@see ResponseParser} enforces the
 * shape contract declared in {@see PromptBuilder::RECOMMENDATION_SCHEMA}.
 *
 * Two failure surfaces are exercised:
 *   - Top-level shape errors (non-object payloads, missing
 *     `recommendations` key) MUST raise {@see AiProviderException} so the
 *     Recommendation_Engine flags the {@code Ai_Analysis} as `failed`
 *     (Requirement 6.9).
 *   - Item-level errors (missing required keys) MUST silently drop the
 *     offending recommendation while preserving the rest of the batch.
 *
 * The suite is fully isolated — no DB, no HTTP, no real logger — and
 * extends `\PHPUnit\Framework\TestCase` directly. A {@see NullLogger} is
 * bound as the `log` resolver on the Laravel Facade container in
 * {@see self::setUp()} so the `Log::warning()` calls inside
 * {@see ResponseParser::parse()} degrade to a silent no-op without
 * requiring a full Laravel boot.
 *
 * Validates: Requirements 6.6, 6.7
 */
class ResponseParserTest extends TestCase
{
    /**
     * Bind a no-op logger as the `log` resolver on the Laravel Facade
     * container so {@see ResponseParser::parse()}'s `Log::warning()`
     * calls degrade to a silent no-op during these pure unit tests
     * (no Laravel application is booted by this base class).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $container = Container::getInstance() ?: new Container();
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        $container->instance('log', new NullLogger());
    }

    /**
     * Build an {@see AiResponse} DTO populated with deterministic values
     * so the parser sees a realistic envelope. The raw text is supplied
     * separately because some tests rely on its `raw_excerpt` substring
     * appearing in the exception context.
     *
     * @param array<int|string,mixed> $parsed
     */
    private function makeResponse(array $parsed, string $raw = '{"recommendations":[]}'): AiResponse
    {
        return new AiResponse($raw, $parsed, 100, 50, 0.001, 'test-model');
    }

    /**
     * Build a fully valid recommendation item satisfying every required
     * key declared by {@see PromptBuilder::RECOMMENDATION_SCHEMA}.
     *
     * @return array<string,mixed>
     */
    private function validRec(): array
    {
        return [
            'action_type' => 'adjust_budget',
            'severity'    => 'medium',
            'rationale'   => 'CTR is below benchmark and CPA is rising; reduce daily budget by 15%.',
            'payload'     => ['daily_budget' => 25.00],
        ];
    }

    /**
     * Happy path: a well-formed response with a single valid item MUST
     * round-trip through the parser unchanged.
     */
    public function testParseExtractsRecommendationsArray(): void
    {
        $rec = $this->validRec();
        $response = $this->makeResponse(['recommendations' => [$rec]]);

        $result = (new ResponseParser())->parse($response, PromptBuilder::RECOMMENDATION_SCHEMA);

        $this->assertCount(1, $result);
        $this->assertSame($rec, $result[0]);
    }

    /**
     * A parsed payload that is not a JSON object (e.g. a list / sequential
     * array) MUST be rejected so the engine never treats an empty list as
     * a healthy outcome (Requirement 6.9 trigger).
     */
    public function testParseThrowsWhenParsedIsNotAnObject(): void
    {
        $response = $this->makeResponse(['just', 'a', 'list']);

        $this->expectException(AiProviderException::class);

        (new ResponseParser())->parse($response, PromptBuilder::RECOMMENDATION_SCHEMA);
    }

    /**
     * A response without a `recommendations` key violates the top-level
     * schema contract and MUST raise {@see AiProviderException}.
     */
    public function testParseThrowsWhenRecommendationsKeyMissing(): void
    {
        $response = $this->makeResponse(['summary' => 'ok']);

        $this->expectException(AiProviderException::class);

        (new ResponseParser())->parse($response, PromptBuilder::RECOMMENDATION_SCHEMA);
    }

    /**
     * Item-level enforcement: a batch with one valid rec, one missing
     * `rationale` and one missing `payload` MUST return ONLY the valid
     * rec. The two offenders are silently dropped (logged at WARNING)
     * without aborting the whole analysis (Requirement 6.6).
     */
    public function testParseFiltersOutRecsMissingRequiredKeys(): void
    {
        $valid = $this->validRec();

        $missingRationale = $valid;
        unset($missingRationale['rationale']);

        $missingPayload = $valid;
        unset($missingPayload['payload']);

        $response = $this->makeResponse([
            'recommendations' => [$valid, $missingRationale, $missingPayload],
        ]);

        $result = (new ResponseParser())->parse($response, PromptBuilder::RECOMMENDATION_SCHEMA);

        $this->assertCount(1, $result, 'Only the fully valid recommendation must survive filtering.');
        $this->assertSame($valid, $result[0]);
    }

    /**
     * An empty `recommendations` array is a perfectly valid outcome
     * (e.g. the target is healthy, no action recommended) and MUST be
     * returned as-is without raising.
     */
    public function testParseAcceptsEmptyRecommendationsArray(): void
    {
        $response = $this->makeResponse(['recommendations' => []]);

        $result = (new ResponseParser())->parse($response, PromptBuilder::RECOMMENDATION_SCHEMA);

        $this->assertSame([], $result);
    }
}
