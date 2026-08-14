<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Models;

use Aero\MasterAds\Models\Insight;
use PluginTestCase;

/**
 * InsightTest — Validates that
 *   - `timestamps` is disabled (Requirement 4.2: append-only ingest),
 *   - `upsertByEntityDate` is idempotent (Property P4),
 *   - `scopeLookback` constrains the window to the last N days.
 */
class InsightTest extends PluginTestCase
{
    public function testTimestampsDisabled(): void
    {
        // Plain object — no DB roundtrip — must report `$timestamps = false`.
        $insight = new Insight();
        $this->assertFalse($insight->timestamps);
    }

    public function testUpsertByEntityDateIdempotent(): void
    {
        $payloadV1 = [
            'entity_type' => 'campaign',
            'entity_id'   => 42,
            'date'        => '2025-01-15',
            'impressions' => 100,
            'clicks'      => 10,
            'spend'       => 1.2345,
            'conversions' => 1,
        ];

        Insight::upsertByEntityDate($payloadV1);
        $this->assertSame(1, Insight::count());

        // Same natural key but updated metric values → "last write wins"
        // semantics with no extra row.
        $payloadV2 = array_merge($payloadV1, [
            'impressions' => 200,
            'clicks'      => 20,
            'spend'       => 2.5,
            'conversions' => 3,
        ]);

        Insight::upsertByEntityDate($payloadV2);
        $this->assertSame(1, Insight::count());

        $row = Insight::forEntity('campaign', 42)->first();
        $this->assertSame(200, (int) $row->impressions);
        $this->assertSame(20, (int) $row->clicks);
        $this->assertSame(3, (int) $row->conversions);
        // spend is decimal:4 — compare as float to avoid string-format flakiness.
        $this->assertEqualsWithDelta(2.5, (float) $row->spend, 0.0001);
    }

    public function testLookbackScope(): void
    {
        // Five insights spread across ten days. The scope is "date >= today - N",
        // so with lookback(3) only the entries dated within the last 3 days
        // should survive: today and 2 days ago.
        $today    = now()->toDateString();
        $twoBack  = now()->subDays(2)->toDateString();
        $fourBack = now()->subDays(4)->toDateString();
        $sevenBack = now()->subDays(7)->toDateString();
        $tenBack   = now()->subDays(10)->toDateString();

        foreach (
            [
                ['ad', 1, $today],
                ['ad', 1, $twoBack],
                ['ad', 1, $fourBack],
                ['ad', 1, $sevenBack],
                ['ad', 1, $tenBack],
            ] as [$type, $id, $date]
        ) {
            Insight::create([
                'entity_type' => $type,
                'entity_id'   => $id,
                'date'        => $date,
                'impressions' => 1,
                'clicks'      => 0,
                'spend'       => 0,
                'conversions' => 0,
            ]);
        }

        // Sanity: all five rows persisted.
        $this->assertSame(5, Insight::count());

        // lookback(3) keeps `today` and `2 days ago`, drops the rest.
        $this->assertSame(2, Insight::lookback(3)->count());
    }
}
