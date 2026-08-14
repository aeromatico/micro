<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Engine;

use Aero\MasterAds\Classes\Engine\MetricsAggregator;
use Aero\MasterAds\Models\Insight;
use PHPUnit\Framework\TestCase;

/**
 * MetricsAggregatorTest — Pure unit tests over the stateless aggregation
 * service. Extends {@see TestCase} directly: there is no database, no
 * container, no facade in play.
 *
 * Coverage:
 *   - `initialSnapshot()`: every counter starts at zero, including the
 *     `unique_dates` set and `days_in_window` cardinality.
 *   - `fold()`: accumulates all numeric counters and tracks the distinct
 *     calendar dates registered through `Insight.date`.
 *   - `finalize()`: derives the seven KPIs (CTR, CPC, CPM, ROAS, CPA,
 *     conversion_rate, frequency) AND degrades to 0.0 on a zero
 *     denominator instead of throwing or producing NaN.
 *
 * The Insight collaborator is stubbed via an anonymous subclass that
 * skips Eloquent's constructor (so no DB / no model boot) and overrides
 * `getAttribute()` to read from a plain associative array. This is
 * sufficient because {@see MetricsAggregator::fold()} only depends on
 * attribute access — never on relations, queries or the model's
 * lifecycle hooks.
 *
 * Validates: Requirements 6.4, 14.5.
 */
class MetricsAggregatorTest extends TestCase
{
    /**
     * Build an Insight stub from a flat attribute map.
     *
     * The anonymous subclass deliberately skips `parent::__construct()`
     * to avoid Eloquent's model boot (events, attributes filling, mass
     * assignment guards) — none of which {@see MetricsAggregator::fold()}
     * needs. `getAttribute()` is rewired to a direct lookup so both magic
     * property access (`$i->impressions` → `__get` → `getAttribute`) and
     * explicit calls (`$i->getAttribute('revenue')`) resolve from the
     * test fixture array.
     *
     * @param  array<string,mixed> $attrs
     */
    private function makeInsight(array $attrs): Insight
    {
        return new class($attrs) extends Insight {
            public function __construct(array $stub)
            {
                // Intentionally skip parent::__construct() to bypass
                // Eloquent's boot — fold() only ever reads attributes.
                $this->attributes = $stub;
            }

            public function getAttribute($key)
            {
                return $this->attributes[$key] ?? null;
            }
        };
    }

    /**
     * Convenience: build the canonical zeroed snapshot via the unit-
     * under-test so we never hard-code the keys in fixtures.
     *
     * @return array<string,mixed>
     */
    private function emptySnapshot(): array
    {
        return (new MetricsAggregator())->initialSnapshot();
    }

    // ------------------------------------------------------------------
    // initialSnapshot()
    // ------------------------------------------------------------------

    /**
     * `initialSnapshot()` MUST produce a fully-zeroed accumulator with
     * the documented schema. This is the canonical starting state of the
     * fold loop and the canonical shape later persisted in
     * `AiAnalysis.metrics_snapshot` (Requirement 6.4).
     */
    public function testInitialSnapshotHasZeroCounters(): void
    {
        $snapshot = (new MetricsAggregator())->initialSnapshot();

        $this->assertSame(0,    $snapshot['impressions']);
        $this->assertSame(0,    $snapshot['clicks']);
        $this->assertSame(0.0,  $snapshot['spend']);
        $this->assertSame(0,    $snapshot['conversions']);
        $this->assertSame(0,    $snapshot['video_views']);
        $this->assertSame(0.0,  $snapshot['revenue']);
        $this->assertSame(0,    $snapshot['reach']);
        $this->assertSame(0.0,  $snapshot['frequency']);
        $this->assertSame(0,    $snapshot['days_in_window']);
        $this->assertSame([],   $snapshot['unique_dates']);
    }

    // ------------------------------------------------------------------
    // fold()
    // ------------------------------------------------------------------

    /**
     * Folding three Insight rows MUST accumulate every numeric counter
     * (core + optional columns), confirming the aggregator is a faithful
     * sum over the input stream.
     */
    public function testFoldAccumulatesCounters(): void
    {
        $agg = new MetricsAggregator();
        $snapshot = $agg->initialSnapshot();

        $rows = [
            $this->makeInsight([
                'impressions' => 100, 'clicks' => 10, 'spend' => 5.0,
                'conversions' => 1, 'video_views' => 20,
                'revenue' => 8.0, 'reach' => 50, 'frequency' => 1.2,
                'date' => '2025-01-01',
            ]),
            $this->makeInsight([
                'impressions' => 200, 'clicks' => 25, 'spend' => 12.5,
                'conversions' => 3, 'video_views' => 40,
                'revenue' => 22.0, 'reach' => 80, 'frequency' => 1.5,
                'date' => '2025-01-02',
            ]),
            $this->makeInsight([
                'impressions' => 300, 'clicks' => 30, 'spend' => 18.25,
                'conversions' => 5, 'video_views' => 60,
                'revenue' => 30.0, 'reach' => 120, 'frequency' => 2.0,
                'date' => '2025-01-03',
            ]),
        ];

        foreach ($rows as $r) {
            $agg->fold($snapshot, $r);
        }

        $this->assertSame(600,                       $snapshot['impressions']);
        $this->assertSame(65,                        $snapshot['clicks']);
        $this->assertEqualsWithDelta(35.75, $snapshot['spend'],     0.0001);
        $this->assertSame(9,                         $snapshot['conversions']);
        $this->assertSame(120,                       $snapshot['video_views']);
        $this->assertEqualsWithDelta(60.0,  $snapshot['revenue'],   0.0001);
        $this->assertSame(250,                       $snapshot['reach']);
        $this->assertEqualsWithDelta(4.7,   $snapshot['frequency'], 0.0001);
        $this->assertSame(3, $snapshot['days_in_window']);
    }

    /**
     * `days_in_window` MUST equal the cardinality of distinct `Insight.date`
     * values seen, not the row count. Three insights spread over two
     * calendar dates yield `days_in_window = 2` (Requirement 14.5: the
     * lookback day count tracks unique dates, robust to duplicates).
     */
    public function testFoldTracksUniqueDates(): void
    {
        $agg = new MetricsAggregator();
        $snapshot = $agg->initialSnapshot();

        $agg->fold($snapshot, $this->makeInsight(['date' => '2025-01-01', 'impressions' => 1]));
        $agg->fold($snapshot, $this->makeInsight(['date' => '2025-01-01', 'impressions' => 1]));
        $agg->fold($snapshot, $this->makeInsight(['date' => '2025-01-02', 'impressions' => 1]));

        $this->assertSame(2, $snapshot['days_in_window']);
        $this->assertSame(
            ['2025-01-01' => true, '2025-01-02' => true],
            $snapshot['unique_dates']
        );
    }

    /**
     * Folding a `DateTimeInterface` instance into `Insight.date` MUST
     * register the same Y-m-d key as the equivalent string, keeping the
     * uniqueness set canonical regardless of input shape.
     */
    public function testFoldAcceptsDateTimeInterfaceForDate(): void
    {
        $agg = new MetricsAggregator();
        $snapshot = $agg->initialSnapshot();

        $agg->fold($snapshot, $this->makeInsight([
            'date'        => new \DateTimeImmutable('2025-01-01 13:00:00'),
            'impressions' => 1,
        ]));
        $agg->fold($snapshot, $this->makeInsight([
            'date'        => '2025-01-01',
            'impressions' => 1,
        ]));

        $this->assertSame(1, $snapshot['days_in_window']);
        $this->assertArrayHasKey('2025-01-01', $snapshot['unique_dates']);
    }

    /**
     * Null optional columns MUST coerce to 0 so the fold remains a total
     * function over the entire Insight schema (including rows missing
     * `revenue`, `reach`, `frequency`, `video_views`).
     */
    public function testFoldTreatsNullOptionalColumnsAsZero(): void
    {
        $agg = new MetricsAggregator();
        $snapshot = $agg->initialSnapshot();

        // Only core columns populated — all optional fields null/absent.
        $agg->fold($snapshot, $this->makeInsight([
            'impressions' => 50, 'clicks' => 5, 'spend' => 2.5,
            'conversions' => 1, 'date' => '2025-01-01',
        ]));

        $this->assertSame(50,  $snapshot['impressions']);
        $this->assertSame(5,   $snapshot['clicks']);
        $this->assertEqualsWithDelta(2.5, $snapshot['spend'], 0.0001);
        $this->assertSame(1,   $snapshot['conversions']);
        $this->assertSame(0,   $snapshot['video_views']);
        $this->assertSame(0.0, $snapshot['revenue']);
        $this->assertSame(0,   $snapshot['reach']);
        $this->assertSame(0.0, $snapshot['frequency']);
    }

    // ------------------------------------------------------------------
    // finalize() — happy paths
    // ------------------------------------------------------------------

    /**
     * CTR = clicks / impressions * 100. 20 clicks over 1 000 impressions
     * yields a 2.0 % CTR.
     */
    public function testFinalizeComputesCtr(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'impressions' => 1000,
                'clicks'      => 20,
            ])
        );

        $this->assertEqualsWithDelta(2.0, $kpis['ctr'], 0.0001);
    }

    /**
     * CPC = spend / clicks. $25 spend over 10 clicks → $2.50 per click.
     */
    public function testFinalizeComputesCpc(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'spend'  => 25.0,
                'clicks' => 10,
            ])
        );

        $this->assertEqualsWithDelta(2.5, $kpis['cpc'], 0.0001);
    }

    /**
     * CPM = spend / impressions * 1000. $10 spend over 1 000 impressions
     * yields a $10 CPM.
     */
    public function testFinalizeComputesCpm(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'spend'       => 10.0,
                'impressions' => 1000,
            ])
        );

        $this->assertEqualsWithDelta(10.0, $kpis['cpm'], 0.0001);
    }

    /**
     * ROAS = revenue / spend. $200 revenue on $100 spend → 2.0 ROAS.
     */
    public function testFinalizeComputesRoas(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'revenue' => 200.0,
                'spend'   => 100.0,
            ])
        );

        $this->assertEqualsWithDelta(2.0, $kpis['roas'], 0.0001);
    }

    /**
     * CPA = spend / conversions. $100 spend on 10 conversions → $10 CPA.
     */
    public function testFinalizeComputesCpa(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'spend'       => 100.0,
                'conversions' => 10,
            ])
        );

        $this->assertEqualsWithDelta(10.0, $kpis['cpa'], 0.0001);
    }

    /**
     * conversion_rate = conversions / clicks * 100. 10 conversions on
     * 100 clicks → 10 % conversion rate.
     */
    public function testFinalizeComputesConversionRate(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'conversions' => 10,
                'clicks'      => 100,
            ])
        );

        $this->assertEqualsWithDelta(10.0, $kpis['conversion_rate'], 0.0001);
    }

    /**
     * frequency (derived) = impressions / reach. 1 000 impressions on
     * 500 unique reached users → an average frequency of 2.0.
     */
    public function testFinalizeComputesFrequency(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'impressions' => 1000,
                'reach'       => 500,
            ])
        );

        $this->assertEqualsWithDelta(2.0, $kpis['frequency'], 0.0001);
    }

    /**
     * Smoke-test that the multi-KPI happy path returns the expected
     * shape: every KPI is present, every value is a float. Catches
     * regressions where a key would silently disappear from the output.
     */
    public function testFinalizeReturnsAllSevenKpis(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'impressions' => 1000,
                'clicks'      => 20,
                'spend'       => 50.0,
                'conversions' => 5,
                'revenue'     => 75.0,
                'reach'       => 800,
            ])
        );

        foreach (['ctr', 'cpc', 'cpm', 'roas', 'cpa', 'conversion_rate', 'frequency'] as $key) {
            $this->assertArrayHasKey($key, $kpis, "KPI '{$key}' missing from finalize() output.");
            $this->assertIsFloat($kpis[$key], "KPI '{$key}' must be a float.");
        }
    }

    // ------------------------------------------------------------------
    // finalize() — safe division by zero
    // ------------------------------------------------------------------

    /**
     * CTR with zero impressions MUST return 0.0 — not throw, not NaN.
     * The 0.0 means "insufficient data", not "0 % of impressions clicked".
     */
    public function testFinalizeSafeDivisionByZero(): void
    {
        $kpis = (new MetricsAggregator())->finalize($this->emptySnapshot());

        $this->assertSame(0.0, $kpis['ctr']);
        // Defensive: confirm no NaN slipped through.
        $this->assertFalse(is_nan($kpis['ctr']));
    }

    /**
     * CPC with zero clicks MUST return 0.0 instead of dividing by zero.
     */
    public function testFinalizeCpcSafeDivisionByZero(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'spend'  => 100.0,
                'clicks' => 0,
            ])
        );

        $this->assertSame(0.0, $kpis['cpc']);
        $this->assertFalse(is_nan($kpis['cpc']));
    }

    /**
     * CPM with zero impressions MUST return 0.0.
     */
    public function testFinalizeCpmSafeDivisionByZero(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'spend'       => 100.0,
                'impressions' => 0,
            ])
        );

        $this->assertSame(0.0, $kpis['cpm']);
        $this->assertFalse(is_nan($kpis['cpm']));
    }

    /**
     * ROAS with zero spend MUST return 0.0 (no spend → ratio is
     * undefined, treat as "insufficient data").
     */
    public function testFinalizeRoasSafeDivisionByZero(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'revenue' => 200.0,
                'spend'   => 0.0,
            ])
        );

        $this->assertSame(0.0, $kpis['roas']);
        $this->assertFalse(is_nan($kpis['roas']));
    }

    /**
     * CPA with zero conversions MUST return 0.0.
     */
    public function testFinalizeCpaSafeDivisionByZero(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'spend'       => 100.0,
                'conversions' => 0,
            ])
        );

        $this->assertSame(0.0, $kpis['cpa']);
        $this->assertFalse(is_nan($kpis['cpa']));
    }

    /**
     * conversion_rate with zero clicks MUST return 0.0.
     */
    public function testFinalizeConversionRateSafeDivisionByZero(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'conversions' => 10,
                'clicks'      => 0,
            ])
        );

        $this->assertSame(0.0, $kpis['conversion_rate']);
        $this->assertFalse(is_nan($kpis['conversion_rate']));
    }

    /**
     * frequency (derived) with zero reach MUST return 0.0.
     */
    public function testFinalizeFrequencySafeDivisionByZero(): void
    {
        $kpis = (new MetricsAggregator())->finalize(
            array_merge($this->emptySnapshot(), [
                'impressions' => 1000,
                'reach'       => 0,
            ])
        );

        $this->assertSame(0.0, $kpis['frequency']);
        $this->assertFalse(is_nan($kpis['frequency']));
    }
}
