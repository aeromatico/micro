<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Properties;

use Aero\MasterAds\Classes\Ai\RecommendationValidator;
use Aero\MasterAds\Models\Ad;
use Aero\MasterAds\Models\AdSet;
use Aero\MasterAds\Models\Campaign;
use PHPUnit\Framework\TestCase;

/**
 * Property P8 — Schema de respuesta IA.
 *
 * Validates: Property P8 / Requirements 6.6, 6.7.
 *
 * Formal statement (from design.md):
 *
 *     property_recommendation_schema: ∀ r ∈ Recommendation:
 *         validate(r.payload, schema_for(r.action_type)) == true
 *
 * Operationally, for every Recommendation item the engine receives from
 * the AI provider:
 *
 *     RecommendationValidator::validate($rec, $target) === true
 *     ⇔
 *     ($rec satisfies the action-specific payload schema) ∧
 *     ($rec satisfies the common rules of Requirement 6.7)
 *
 * Common rules (Req 6.7):
 *   - severity   ∈ {low, medium, high, critical}
 *   - rationale  is a string with length ≥ 10
 *   - confidence (if present) is numeric in [0, 100]
 *   - expected_impact_pct (if present) is numeric
 *
 * Per-action_type rules (Req 6.6):
 *   - adjust_budget:   payload.daily_budget numeric > 0
 *   - pause / resume:  payload may be empty {}
 *   - scale:           payload.multiplier numeric > 0 and ≤ 10
 *   - change_audience: target instanceof AdSet ∧ payload.targeting non-empty array
 *   - change_creative: target instanceof Ad    ∧ payload.creative_id non-empty string
 *                                                OR payload.creative non-empty array
 *   - any other action_type ⇒ false
 *
 * The property is exercised by a PHPUnit data-provider acting as the
 * generator: each yielded array is one randomized candidate. The "valid"
 * provider yields recommendations that MUST be accepted; the "invalid"
 * provider yields recommendations each of which violates exactly one rule
 * and MUST be rejected. Two example-based tests cover the target-type
 * invariants for `change_audience` and `change_creative`.
 *
 * No database, no service container, no I/O — the validator is a pure
 * function of (array, target instance), and the targets are anonymous-class
 * stubs whose constructor is short-circuited to skip Eloquent boot.
 */
final class AiResponseSchemaTest extends TestCase
{
    /**
     * Generator: 20 random valid recommendations covering each of the four
     * Campaign-compatible action_types (adjust_budget, pause, resume, scale).
     * Each recommendation is built from {@see self::baseRec()} (which
     * randomises `severity`) and gets a payload appropriate to its kind.
     *
     * @return iterable<int, array{0: array<string,mixed>}>
     */
    public static function validRecsForCampaign(): iterable
    {
        $kinds = ['adjust_budget', 'pause', 'resume', 'scale'];
        foreach (range(1, 20) as $i) {
            $kind = $kinds[$i % count($kinds)];
            $rec = self::baseRec($kind);
            // payload appropriate to each kind
            $rec['payload'] = match ($kind) {
                'adjust_budget' => ['daily_budget' => mt_rand(1, 10000) / 10],
                'pause', 'resume' => [],
                'scale' => ['multiplier' => mt_rand(1, 100) / 10],
            };
            yield [$rec];
        }
    }

    /**
     * Generator: handcrafted recommendations each violating exactly one
     * rule from Req 6.6 or Req 6.7. Each must be rejected by the validator.
     *
     * @return iterable<string, array{0: array<string,mixed>}>
     */
    public static function invalidRecs(): iterable
    {
        // Each one intentionally violates one rule.
        yield 'missing_severity' => [[
            'action_type' => 'pause',
            'rationale'   => 'long enough rationale',
            'payload'     => [],
        ]];
        yield 'invalid_severity' => [[
            'action_type' => 'pause',
            'severity'    => 'extreme',
            'rationale'   => 'reason text',
            'payload'     => [],
        ]];
        yield 'short_rationale' => [[
            'action_type' => 'pause',
            'severity'    => 'low',
            'rationale'   => 'too',
            'payload'     => [],
        ]];
        yield 'unknown_action_type' => [[
            'action_type' => 'evaporate',
            'severity'    => 'low',
            'rationale'   => 'reason text here',
            'payload'     => [],
        ]];
        yield 'adjust_budget_zero' => [[
            'action_type' => 'adjust_budget',
            'severity'    => 'low',
            'rationale'   => 'reason text here',
            'payload'     => ['daily_budget' => 0],
        ]];
        yield 'adjust_budget_negative' => [[
            'action_type' => 'adjust_budget',
            'severity'    => 'low',
            'rationale'   => 'reason text here',
            'payload'     => ['daily_budget' => -50],
        ]];
        yield 'adjust_budget_non_numeric' => [[
            'action_type' => 'adjust_budget',
            'severity'    => 'low',
            'rationale'   => 'reason text here',
            'payload'     => ['daily_budget' => 'not-a-number'],
        ]];
        yield 'scale_too_high' => [[
            'action_type' => 'scale',
            'severity'    => 'high',
            'rationale'   => 'reason text here',
            'payload'     => ['multiplier' => 11],
        ]];
        yield 'scale_zero' => [[
            'action_type' => 'scale',
            'severity'    => 'low',
            'rationale'   => 'reason text here',
            'payload'     => ['multiplier' => 0],
        ]];
        yield 'confidence_over_100' => [[
            'action_type' => 'pause',
            'severity'    => 'low',
            'rationale'   => 'reason text here',
            'payload'     => [],
            'confidence'  => 150,
        ]];
        yield 'confidence_negative' => [[
            'action_type' => 'pause',
            'severity'    => 'low',
            'rationale'   => 'reason text here',
            'payload'     => [],
            'confidence'  => -5,
        ]];
        yield 'expected_impact_non_numeric' => [[
            'action_type'         => 'pause',
            'severity'            => 'low',
            'rationale'           => 'reason text here',
            'payload'             => [],
            'expected_impact_pct' => 'lots',
        ]];
    }

    /**
     * Base recommendation factory used by {@see self::validRecsForCampaign()}.
     * Picks a random severity from the enum and uses a rationale that is
     * comfortably longer than the 10-char minimum.
     *
     * @return array<string,mixed>
     */
    private static function baseRec(string $kind): array
    {
        return [
            'action_type' => $kind,
            'severity'    => ['low', 'medium', 'high', 'critical'][mt_rand(0, 3)],
            'rationale'   => 'A reasonable rationale longer than ten chars.',
            'payload'     => [],
        ];
    }

    /**
     * P8 — Acceptance direction: every recommendation produced by the
     * `validRecsForCampaign` generator must be accepted against a Campaign
     * target. Failure here means the validator is *too strict*, i.e. it
     * rejects a payload that satisfies its action-type schema.
     *
     * @dataProvider validRecsForCampaign
     * @param array<string,mixed> $rec
     */
    public function testValidatorAcceptsValidRecsForCampaign(array $rec): void
    {
        $target = $this->makeCampaignStub();
        $validator = new RecommendationValidator();
        $this->assertTrue(
            $validator->validate($rec, $target),
            'Valid rec must pass: ' . json_encode($rec)
        );
    }

    /**
     * P8 — Rejection direction: every recommendation produced by the
     * `invalidRecs` generator violates exactly one rule and must be
     * rejected. Failure here means the validator is *too permissive*,
     * i.e. it would allow a malformed recommendation to be persisted
     * (Req 6.6 / 6.7 contract broken).
     *
     * @dataProvider invalidRecs
     * @param array<string,mixed> $rec
     */
    public function testValidatorRejectsInvalidRecs(array $rec): void
    {
        $target = $this->makeCampaignStub();
        $validator = new RecommendationValidator();
        $this->assertFalse(
            $validator->validate($rec, $target),
            'Invalid rec must be rejected: ' . json_encode($rec)
        );
    }

    /**
     * P8 — Target-type invariant for `change_audience`. The action only
     * makes sense at the AdSet level (audience lives on AdSets), so the
     * validator must reject a Campaign target and accept an AdSet target
     * for an otherwise identical recommendation.
     */
    public function testChangeAudienceRequiresAdSet(): void
    {
        $validator = new RecommendationValidator();
        $rec = [
            'action_type' => 'change_audience',
            'severity'    => 'medium',
            'rationale'   => 'audience tweak needed',
            'payload'     => ['targeting' => ['age_min' => 18]],
        ];

        $this->assertFalse(
            $validator->validate($rec, $this->makeCampaignStub()),
            'change_audience must reject Campaign target'
        );
        $this->assertTrue(
            $validator->validate($rec, $this->makeAdSetStub()),
            'change_audience must accept AdSet target'
        );
    }

    /**
     * P8 — Target-type invariant for `change_creative`. The action only
     * makes sense at the Ad level (creatives live on Ads), so the
     * validator must reject an AdSet target and accept an Ad target for
     * an otherwise identical recommendation.
     */
    public function testChangeCreativeRequiresAd(): void
    {
        $validator = new RecommendationValidator();
        $rec = [
            'action_type' => 'change_creative',
            'severity'    => 'high',
            'rationale'   => 'creative is stale',
            'payload'     => ['creative_id' => 'crv_1'],
        ];

        $this->assertFalse(
            $validator->validate($rec, $this->makeAdSetStub()),
            'change_creative must reject AdSet target'
        );
        $this->assertTrue(
            $validator->validate($rec, $this->makeAdStub()),
            'change_creative must accept Ad target'
        );
    }

    /**
     * Anonymous-class Campaign stub. The empty constructor short-circuits
     * Eloquent's boot/Validation trait wiring so we get a real `instanceof`
     * Campaign object without any DB access.
     */
    private function makeCampaignStub(): Campaign
    {
        return new class extends Campaign {
            public function __construct() {}
        };
    }

    /**
     * Anonymous-class AdSet stub. Same shortcut: bare `instanceof AdSet`
     * for the target-type branches in {@see RecommendationValidator}.
     */
    private function makeAdSetStub(): AdSet
    {
        return new class extends AdSet {
            public function __construct() {}
        };
    }

    /**
     * Anonymous-class Ad stub. Same shortcut: bare `instanceof Ad` for the
     * `change_creative` branch in {@see RecommendationValidator}.
     */
    private function makeAdStub(): Ad
    {
        return new class extends Ad {
            public function __construct() {}
        };
    }
}
