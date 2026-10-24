<?php namespace Aero\Producer\Tests;

use Aero\Producer\Models\Order;
use Aero\Producer\Models\Style;
use Aero\Producer\Models\Voice;
use PluginTestCase;

class OrderTest extends PluginTestCase
{
    protected function presenter(): Style
    {
        return Style::where('slug', 'presentador-calle')->firstOrFail();
    }

    public function testSeededStylesExist(): void
    {
        $this->assertSame(['explainer', 'presenter'], Style::orderBy('kind')->pluck('kind')->all());
    }

    public function testEmptyOrderListsWhatIsMissing(): void
    {
        $order = Order::create(['tenant_id' => 1, 'style_id' => $this->presenter()->id, 'name' => 'Anuncio']);

        $this->assertSame(
            ['Falta el guion.', 'Falta elegir una voz.', 'Falta la imagen del presentador.'],
            $order->problems()
        );
    }

    public function testScriptLongerThanStyleLimitIsRejected(): void
    {
        $style = $this->presenter();
        $voice = Voice::create(['provider' => 'elevenlabs', 'voice_id' => 'v1', 'name' => 'Luisa']);
        $order = Order::create([
            'tenant_id' => 1, 'style_id' => $style->id, 'name' => 'Largo', 'voice_id' => $voice->id,
            'script' => implode(' ', array_fill(0, 120, 'palabra')),
        ]);

        $this->assertGreaterThan($style->max_seconds, $order->estimated_seconds);
        $this->assertStringContainsString('este estilo admite 30 s', $order->problems()[0]);
    }

    public function testOrdersBelongToTheirTenant(): void
    {
        $style = $this->presenter();
        Order::create(['tenant_id' => 1, 'style_id' => $style->id, 'name' => 'A']);
        Order::create(['tenant_id' => 2, 'style_id' => $style->id, 'name' => 'B']);

        $this->assertSame(['A'], Order::where('tenant_id', 1)->pluck('name')->all());
    }

    public function testSentOrdersAreNotEditable(): void
    {
        $order = Order::create(['tenant_id' => 1, 'style_id' => $this->presenter()->id, 'name' => 'A']);
        $this->assertTrue($order->is_editable);

        $order->status = 'queued';
        $this->assertFalse($order->is_editable);
    }
}
