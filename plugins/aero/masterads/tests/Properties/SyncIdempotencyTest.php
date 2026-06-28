<?php declare(strict_types=1);

namespace Aero\MasterAds\Tests\Properties;

use Aero\MasterAds\Models\Insight;
use PluginTestCase;

/**
 * Property P4 — Idempotencia de sync / Sync Idempotency.
 *
 * Validates: Property P4 / Requirements 4.2, 4.3.
 *
 * For every MetaAccount and every date d:
 *   Running `Insight::upsertByEntityDate()` N times for the same
 *   (entity_type, entity_id, date) tuple results in:
 *     - count(Insight) staying constant after the first execution
 *     - last-write-wins semantics for the metric columns
 *     - no duplicate rows for the same natural key
 *
 * The natural key is enforced by a UNIQUE index in
 * `aero_masterads_insights(entity_type, entity_id, date)`.
 */
class SyncIdempotencyTest extends PluginTestCase
{
    public static function repeatCountProvider(): iterable
    {
        yield 'twice' => [2];
        yield 'five-times' => [5];
        yield 'ten-times' => [10];
    }

    public static function concurrentBatchProvider(): iterable
    {
        yield 'two' => [2];
        yield 'five' => [5];
        yield 'twenty' => [20];
    }

    /** @dataProvider repeatCountProvider */
    public function testInsightUpsertIsIdempotent(int $n): void
    {
        $payloads = $this->randomPayloadBatch();

        $initialCount = Insight::count();

        // First pass: insert all 5
        foreach ($payloads as $p) {
            Insight::upsertByEntityDate($p);
        }
        $firstCount = Insight::count();
        $this->assertSame($initialCount + 5, $firstCount,
            'First pass must insert exactly 5 rows for 5 unique keys');

        // N-1 more passes with SAME payloads — count must stay at firstCount
        for ($i = 1; $i < $n; $i++) {
            foreach ($payloads as $p) {
                Insight::upsertByEntityDate($p);
            }
            $this->assertSame($firstCount, Insight::count(),
                "Sync idempotency violated on pass {$i}: count grew");
        }
    }

    public function testLastWriteWinsOnSameKey(): void
    {
        $payload = [
            'entity_type' => 'ad',
            'entity_id' => 42,
            'date' => '2025-01-15',
            'impressions' => 100,
            'clicks' => 10,
            'spend' => 1.0,
            'conversions' => 1,
        ];
        Insight::upsertByEntityDate($payload);

        $updated = array_merge($payload, [
            'impressions' => 200, 'clicks' => 20, 'spend' => 2.0, 'conversions' => 2,
        ]);
        Insight::upsertByEntityDate($updated);

        $row = Insight::where('entity_type', 'ad')
            ->where('entity_id', 42)
            ->where('date', '2025-01-15')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(200, (int) $row->impressions);
        $this->assertSame(20, (int) $row->clicks);
        $this->assertSame(2, (int) $row->conversions);
        $this->assertSame(1, Insight::where('entity_type', 'ad')
            ->where('entity_id', 42)
            ->where('date', '2025-01-15')
            ->count(),
            'Only 1 row should exist for the natural key');
    }

    /** @dataProvider concurrentBatchProvider */
    public function testConcurrentDuplicatesProduceNoDuplicates(int $duplicatesPerKey): void
    {
        $baseTuple = [
            'entity_type' => 'campaign',
            'entity_id' => 99,
            'date' => '2025-02-01',
        ];

        for ($i = 0; $i < $duplicatesPerKey; $i++) {
            Insight::upsertByEntityDate(array_merge($baseTuple, [
                'impressions' => 100 + $i,
                'clicks' => 5,
                'spend' => 0.5,
                'conversions' => 0,
            ]));
        }

        $this->assertSame(1, Insight::where('entity_type', 'campaign')
            ->where('entity_id', 99)
            ->where('date', '2025-02-01')
            ->count(),
            "After {$duplicatesPerKey} duplicate upserts, only 1 row must exist");
    }

    private function randomPayloadBatch(): array
    {
        $entityTypes = ['campaign', 'adset', 'ad'];
        $batch = [];
        for ($i = 0; $i < 5; $i++) {
            $month = ($i % 9) + 1;
            $batch[] = [
                'entity_type' => $entityTypes[$i % 3],
                'entity_id' => 1000 + $i,
                'date' => sprintf('2025-%02d-15', $month),
                'impressions' => mt_rand(100, 10000),
                'clicks' => mt_rand(1, 500),
                'spend' => mt_rand(1, 1000) / 10,
                'conversions' => mt_rand(0, 50),
            ];
        }
        return $batch;
    }
}
