<?php declare(strict_types=1);

namespace Aero\MasterAds\Classes\Engine;

use Aero\MasterAds\Models\Insight;
use DateTimeInterface;

/**
 * MetricsAggregator — Pure aggregation service over Insight rows.
 *
 * Stateless, deterministic and free of side effects: every method is a
 * referentially transparent function of its inputs. No I/O, no DB writes,
 * no queries — the aggregator only knows how to fold a stream of already
 * loaded {@see Insight} rows into a running snapshot accumulator and to
 * derive the standard ad-tech KPIs from the resulting totals.
 *
 * ## Single-pass fold semantics (Requirement 14.5)
 *
 * The aggregator is designed so the {@see RecommendationEngine} can walk
 * the lookback window of insights **once** — typically via a generator or
 * a chunked cursor — without ever materialising the full set in memory.
 * That is what realises Acceptance Criterion 14.5:
 *
 *   > "WHEN se ejecuta un análisis IA con `lookback_days`, THE
 *   >  Recommendation_Engine SHALL agregar las métricas en una sola pasada
 *   >  (fold) sin queries N+1 sobre Insight."
 *
 * Algorithm shape (from `design.md` §RecommendationEngine.analyze):
 *
 *   snapshot ← MetricsAggregator.initialSnapshot()
 *   FOR each insight IN target.insights().lookback(period)->cursor() DO
 *       INVARIANT: snapshot contains the exact sum of all insights
 *                  processed so far.
 *       MetricsAggregator.fold(snapshot, insight)
 *   END FOR
 *   derived ← MetricsAggregator.finalize(snapshot)
 *
 * Note that {@see fold()} mutates the snapshot in place — this is the
 * deliberate optimisation that keeps the loop O(1) in extra memory.
 *
 * ## Snapshot shape (Requirement 6.4)
 *
 * The accumulator produced by {@see initialSnapshot()} is the same array
 * later persisted in `AiAnalysis.metrics_snapshot` for reproducibility
 * (Requirement 6.4). It contains:
 *
 *   - impressions    int    Sum of `Insight.impressions`.
 *   - clicks         int    Sum of `Insight.clicks`.
 *   - spend          float  Sum of `Insight.spend` (decimal(12,4) string
 *                           cast to float for arithmetic).
 *   - conversions    int    Sum of `Insight.conversions`.
 *   - video_views    int    Sum of `Insight.video_views` (null → 0).
 *   - revenue        float  Sum of `Insight.revenue` if/when the column
 *                           is added; defaults to 0 in the MVP.
 *   - reach          int    Sum of `Insight.reach` when present;
 *                           defaults to 0.
 *   - frequency      float  Sum of `Insight.frequency` when present;
 *                           defaults to 0 (the *derived* frequency lives
 *                           in {@see finalize()} output).
 *   - days_in_window int    Cardinality of `unique_dates`.
 *   - unique_dates   array  Set-as-map keyed by `Y-m-d` strings; used to
 *                           compute `days_in_window` in O(1) per fold.
 *
 * ## Safe division
 *
 * {@see finalize()} never throws on a zero denominator: it returns 0.0
 * for the affected KPI instead. Callers MUST interpret a 0.0 KPI as
 * "insufficient data to compute the ratio", **not** as a perfect or null
 * outcome — e.g. a 0.0 CTR may mean "no impressions yet", not "0 % of
 * impressions converted to clicks".
 *
 * Validates: Requirements 6.4, 14.5.
 */
final class MetricsAggregator
{
    /**
     * Create an empty snapshot accumulator with every numeric field
     * zeroed and `unique_dates` initialised to an empty set.
     *
     * The returned array is the canonical starting point of the fold
     * loop in {@see RecommendationEngine::analyze()} and the canonical
     * shape later merged with the derived KPIs and persisted in
     * `AiAnalysis.metrics_snapshot` (Requirement 6.4).
     *
     * Validates: Requirements 6.4, 14.5.
     *
     * @return array<string, mixed> Zeroed snapshot with the keys listed
     *                              in the class docblock.
     */
    public function initialSnapshot(): array
    {
        return [
            'impressions'    => 0,
            'clicks'         => 0,
            'spend'          => 0.0,
            'conversions'    => 0,
            'video_views'    => 0,
            'revenue'        => 0.0,
            'reach'          => 0,
            'frequency'      => 0.0,
            'days_in_window' => 0,
            'unique_dates'   => [],
        ];
    }

    /**
     * Fold a single Insight into the running snapshot accumulator.
     *
     * All numeric counters of `$snapshot` are incremented by the
     * corresponding value of `$i`, with `null` coerced to 0 so the
     * method is total over the entire `Insight` schema (including
     * optional columns like `video_views`, `revenue`, `reach` and
     * `frequency` that may not be populated for every row).
     *
     * The Insight's `date` is registered in the `unique_dates`
     * set-as-map, and `days_in_window` is refreshed from the resulting
     * cardinality. This makes lookback day counts robust against
     * duplicate-date insights or sparse windows: only distinct
     * calendar days contribute.
     *
     * Mutates `$snapshot` **by reference** for efficiency: this is the
     * key to running the fold in O(1) extra memory over a streamed
     * cursor of insights (Requirement 14.5).
     *
     * Validates: Requirements 6.4, 14.5.
     *
     * @param array<string, mixed> $snapshot Accumulator updated in place.
     * @param Insight              $i        The Insight row to fold in.
     */
    public function fold(array &$snapshot, Insight $i): void
    {
        // Core daily counters — Insight always carries these.
        $snapshot['impressions'] += (int) ($i->impressions ?? 0);
        $snapshot['clicks']      += (int) ($i->clicks ?? 0);
        $snapshot['spend']       += (float) ($i->spend ?? 0);
        $snapshot['conversions'] += (int) ($i->conversions ?? 0);

        // Optional / nullable columns — defaulted to 0 when absent so
        // the fold remains a total function regardless of which columns
        // a given Insight row populates.
        $snapshot['video_views'] += (int) ($i->video_views ?? 0);
        $snapshot['revenue']     += (float) ($i->getAttribute('revenue') ?? 0);
        $snapshot['reach']       += (int) ($i->getAttribute('reach') ?? 0);
        $snapshot['frequency']   += (float) ($i->getAttribute('frequency') ?? 0);

        // Track distinct calendar dates so `days_in_window` equals the
        // cardinality of the input window, not the row count (which may
        // contain duplicates across entity_type / entity_id).
        $rawDate = $i->date;
        $dateKey = $rawDate instanceof DateTimeInterface
            ? $rawDate->format('Y-m-d')
            : (string) $rawDate;

        $snapshot['unique_dates'][$dateKey] = true;
        $snapshot['days_in_window'] = count($snapshot['unique_dates']);
    }

    /**
     * Derive the standard ad-tech KPIs from a folded snapshot.
     *
     * Every ratio uses **safe division**: when the denominator is 0
     * the KPI is returned as 0.0 instead of throwing or producing NaN.
     * Callers MUST interpret 0.0 as "insufficient data" (e.g. no
     * impressions yet, no spend committed), not as "perfect performance".
     *
     * Formulas (see class docblock for snapshot field semantics):
     *   - ctr             = clicks      / impressions * 100   (percentage)
     *   - cpc             = spend       / clicks
     *   - cpm             = spend       / impressions * 1000
     *   - roas            = revenue     / spend               (0 in MVP unless populated)
     *   - cpa             = spend       / conversions
     *   - conversion_rate = conversions / clicks      * 100   (percentage)
     *   - frequency       = impressions / reach               (avg over window)
     *
     * Note that `frequency` is *derived* here even though `$snapshot`
     * also exposes a `frequency` accumulator: the snapshot field stores
     * the sum of per-row reported frequencies (when present), while
     * this output is the ratio `impressions / reach` over the whole
     * window — the value typically expected on a dashboard.
     *
     * Validates: Requirements 6.4, 14.5.
     *
     * @param  array<string, mixed> $snapshot Snapshot produced by
     *                                        repeated {@see fold()} calls.
     * @return array<string, float>           Map of KPI name to value.
     */
    public function finalize(array $snapshot): array
    {
        $impressions = (int)   ($snapshot['impressions'] ?? 0);
        $clicks      = (int)   ($snapshot['clicks']      ?? 0);
        $spend       = (float) ($snapshot['spend']       ?? 0.0);
        $conversions = (int)   ($snapshot['conversions'] ?? 0);
        $revenue     = (float) ($snapshot['revenue']     ?? 0.0);
        $reach       = (int)   ($snapshot['reach']       ?? 0);

        return [
            'ctr'             => $impressions > 0 ? ($clicks      / $impressions) * 100.0   : 0.0,
            'cpc'             => $clicks      > 0 ?  $spend       / $clicks                 : 0.0,
            'cpm'             => $impressions > 0 ? ($spend       / $impressions) * 1000.0  : 0.0,
            'roas'            => $spend       > 0 ?  $revenue     / $spend                  : 0.0,
            'cpa'             => $conversions > 0 ?  $spend       / $conversions            : 0.0,
            'conversion_rate' => $clicks      > 0 ? ($conversions / $clicks)      * 100.0   : 0.0,
            'frequency'       => $reach       > 0 ?  $impressions / $reach                  : 0.0,
        ];
    }
}
