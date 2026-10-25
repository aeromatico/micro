<?php namespace Aero\Shop\Tests;

use Aero\Shop\Models\Feedback;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/ShopTestCase.php';

/** Opiniones: la política de la tienda decide el estado, solo las aprobadas son públicas y todo respeta al tenant. */
class FeedbackTest extends ShopTestCase
{
    public function testModeDecidesInitialStatus(): void
    {
        $this->assertSame('internal', Feedback::statusForMode('internal'));
        $this->assertSame('approved', Feedback::statusForMode('public'));
        $this->assertSame('pending', Feedback::statusForMode('moderated'));
        $this->assertSame('internal', Feedback::statusForMode('off'));
    }

    public function testOnlyApprovedAreVisibleAndScopedByTenant(): void
    {
        $this->openStore(1);
        $this->openStore(2);
        $row = fn (int $tenant, int $order, string $status) => Feedback::create([
            'tenant_id' => $tenant, 'order_id' => $order, 'rating' => 5, 'status' => $status,
        ]);
        $row(1, 1, 'approved');
        $row(1, 2, 'pending');
        $row(1, 3, 'hidden');
        $row(2, 4, 'approved');

        $this->assertSame(1, Feedback::forTenant(1)->published()->count());
        $this->assertSame(1, Feedback::forTenant(2)->published()->count());
        $this->assertSame(3, Feedback::forTenant(1)->count());
    }

    public function testStarsAndOneOpinionPerOrder(): void
    {
        $this->openStore(1);
        $f = Feedback::create(['tenant_id' => 1, 'order_id' => 7, 'rating' => 4, 'status' => 'internal']);
        $this->assertSame('★★★★☆', $f->stars);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('aero_shop_feedback')->insert(['tenant_id' => 1, 'order_id' => 7, 'rating' => 5, 'status' => 'internal']);
    }
}
