<?php namespace Aero\Finance\Tests;

use Aero\Finance\Models\JournalEntry;
use Aero\Finance\Models\Movement;
use PluginTestCase;

class ListenersTest extends PluginTestCase
{
    protected function order(array $o = []): object
    {
        return (object) ($o + [
            'id' => 77, 'tenant_id' => 1, 'order_number' => 'A-1', 'grand_total' => 113.0, 'tax_total' => 13.0,
            'exchange_rate_snapshot' => 1, 'paid_at' => now(),
            'currency' => (object) ['code' => 'BOB'], 'payment_gateway' => (object) ['driver' => 'pagos_qr'],
        ]);
    }

    public function testShopPaidPostsOnceAndRefundVoids(): void
    {
        $order = $this->order();
        \Event::fire('aero.shop.orderPaid', [$order]);
        \Event::fire('aero.shop.orderPaid', [$order]); // reintento: no duplica

        $this->assertEquals(1, Movement::where('source_type', 'shop_order')->count());
        $m = Movement::first();
        $this->assertSame('income', $m->kind);
        $this->assertSame('1.1.02', $m->cash->code); // QR → Bancos
        $this->assertEquals(113.0, $m->entry->lines->sum('debit'));

        \Event::fire('aero.shop.orderRefunded', [$order]);
        $this->assertSame('void', $m->fresh()->status);
        $this->assertEquals(2, JournalEntry::where('tenant_id', 1)->count()); // original + inverso
    }

    public function testDisabledAutoPostDoesNothing(): void
    {
        \Aero\Finance\Models\FinanceSettings::forTenant(1)->update(['post_shop' => false]);
        \Event::fire('aero.shop.orderPaid', [$this->order()]);
        $this->assertEquals(0, Movement::count());
    }

    public function testGymMembershipPostsToMemberships(): void
    {
        $m = (object) ['id' => 5, 'tenant_id' => 1, 'price' => 150, 'currency' => 'BOB', 'paid_at' => now(),
            'payment_reference' => null, 'member' => (object) ['name' => 'Ana'], 'plan' => (object) ['name' => 'Mensual']];
        \Event::fire('aero.gym.membershipActivated', [$m]);

        $mov = Movement::where('source_type', 'gym_membership')->first();
        $this->assertNotNull($mov);
        $this->assertSame('4.1.02', $mov->category->code);
        $this->assertSame('1.1.01', $mov->cash->code); // sin referencia → Caja
    }
}
