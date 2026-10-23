<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\Billing;
use Aero\Workspaces\Classes\ImageTools;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\TaskRate;
use PluginTestCase;

/**
 * Imágenes de Paola: precio avisado antes de generar, cobro por imagen, reembolso si falla,
 * saldo insuficiente y que la foto real (búsqueda) sea gratis.
 */
class ImageToolsTest extends PluginTestCase
{
    protected ImageFakeLedger $ledger;
    protected int $generated = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->ledger = Billing::$ledger = new ImageFakeLedger();
        ImageTools::$generator = function () {
            $this->generated++;

            return str_repeat('x', 800);
        };

        ImageTools::$storer = fn ($tenantId, $bytes) => 'https://example.test/ia.png';

        $s = new Staff();
        $s->fill(['name' => 'Paola', 'slug' => 'paola', 'role' => 'Imágenes', 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'imagen', 'fee_mode' => 'credits', 'credit_type' => 'bronce', 'is_active' => true]);
        $s->save();
        TaskRate::create(['staff_id' => $s->id, 'task_type' => 'imagen', 'fee' => 4]);
    }

    public function tearDown(): void
    {
        Billing::$ledger = null;
        ImageTools::$generator = null;
        ImageTools::$searcher = null;
        ImageTools::$storer = null;
        parent::tearDown();
    }

    protected function args(array $extra = []): array
    {
        return ['prompt' => 'Recepción moderna de una clínica dental, luz cálida', 'agreed_plan' => 'Generar 1 imagen horizontal por 4 monedas de bronce'] + $extra;
    }

    public function testContextoDiceElPrecioYElSaldo(): void
    {
        $this->ledger->fund(1, 10);
        $c = ImageTools::context([], 1)['generation'];

        $this->assertTrue($c['available']);
        $this->assertSame(4, $c['price_per_image']);
        $this->assertSame(10, $c['balance']);
        $this->assertSame(2, $c['images_you_can_afford']);
    }

    public function testSinConfirmarNoCobraNiGenera(): void
    {
        $this->ledger->fund(1, 10);
        $r = ImageTools::generate($this->args(), 1);

        $this->assertTrue($r['confirm_needed']);
        $this->assertSame(4, $r['price']);
        $this->assertSame(10, $this->ledger->balance(1));
        $this->assertSame(0, $this->generated);
    }

    public function testConfirmadoCobraCuatroYGenera(): void
    {
        $this->ledger->fund(1, 10);
        $r = ImageTools::generate($this->args(['confirm' => true, 'orientation' => 'horizontal']), 1);

        $this->assertTrue($r['generated'] ?? false, json_encode($r));
        $this->assertSame(4, $r['charged']);
        $this->assertSame(6, $this->ledger->balance(1));
        $this->assertSame(1, $this->generated);
        $this->assertNotEmpty($r['url']);
    }

    public function testSaldoInsuficienteNoCobraNiGenera(): void
    {
        $this->ledger->fund(1, 3);
        $r = ImageTools::generate($this->args(['confirm' => true]), 1);

        $this->assertArrayHasKey('error', $r);
        $this->assertSame(3, $this->ledger->balance(1));
        $this->assertSame(0, $this->generated);
    }

    public function testSiFallaElProveedorSeReembolsa(): void
    {
        $this->ledger->fund(1, 10);
        ImageTools::$generator = fn () => throw new \RuntimeException('HTTP 500');
        $r = ImageTools::generate($this->args(['confirm' => true]), 1);

        $this->assertTrue($r['refunded'] ?? false);
        $this->assertSame(10, $this->ledger->balance(1));
    }

    public function testSinPlanAcordadoNoCobra(): void
    {
        $this->ledger->fund(1, 10);
        $r = ImageTools::generate(['prompt' => 'Una clínica dental moderna', 'confirm' => true], 1);

        $this->assertArrayHasKey('error', $r);
        $this->assertSame(10, $this->ledger->balance(1));
    }

    public function testElSaldoEsDelTenantQueLlama(): void
    {
        $this->ledger->fund(2, 10);
        $r = ImageTools::generate($this->args(['confirm' => true]), 1);

        $this->assertArrayHasKey('error', $r);
        $this->assertSame(10, $this->ledger->balance(2));
    }

    public function testSinTarifaNoSeGenera(): void
    {
        TaskRate::where('task_type', 'imagen')->delete();
        $this->ledger->fund(1, 10);

        $this->assertFalse(ImageTools::context([], 1)['generation']['available']);
        $this->assertArrayHasKey('error', ImageTools::generate($this->args(['confirm' => true]), 1));
        $this->assertSame(0, $this->generated);
    }

    public function testBuscarFotoRealEsGratis(): void
    {
        $this->ledger->fund(1, 10);
        ImageTools::$searcher = fn () => ['url' => 'https://images.unsplash.com/x.jpg', 'attribution' => 'Foto de Ana en Unsplash'];
        $r = ImageTools::search(['keywords' => 'dental clinic'], 1);

        $this->assertSame('https://images.unsplash.com/x.jpg', $r['photo']['url']);
        $this->assertTrue($r['free']);
        $this->assertSame(10, $this->ledger->balance(1));
    }

    public function testLasHerramientasDeclaradas(): void
    {
        $this->assertSame(["image_context", "image_search", "image_generate"], array_keys(ImageTools::tools()));
    }
}

class ImageFakeLedger
{
    public array $balances = [];
    public array $txs = [];

    public function fund(int $tenantId, int $amount): void
    {
        $this->balances[$tenantId] = ($this->balances[$tenantId] ?? 0) + $amount;
    }

    public function balance(int $tenantId, ?string $type = null): int
    {
        return $this->balances[$tenantId] ?? 0;
    }

    public function charge(int $tenantId, int $amount, string $actionCode, array $context): int
    {
        if ($this->balance($tenantId) < $amount) {
            throw new \RuntimeException('saldo insuficiente');
        }

        $this->balances[$tenantId] -= $amount;
        $id = count($this->txs) + 1;
        $this->txs[$id] = ['tenant' => $tenantId, 'amount' => $amount, 'refunded' => false];

        return $id;
    }

    public function refund(int $id, string $reason): void
    {
        if (!$this->txs[$id]['refunded']) {
            $this->txs[$id]['refunded'] = true;
            $this->balances[$this->txs[$id]['tenant']] += $this->txs[$id]['amount'];
        }
    }
}
