<?php namespace Aero\Invoicing\Tests;

use Aero\Invoicing\Classes\CufdManager;
use Aero\Invoicing\Classes\Drivers\DriverException;
use Aero\Invoicing\Classes\Drivers\DriverFactory;
use Aero\Invoicing\Classes\Drivers\InvoicingDriver;
use Aero\Invoicing\Classes\Drivers\SimulatedDriver;
use Aero\Invoicing\Classes\InvoiceService;
use Aero\Invoicing\Models\Cufd;
use Aero\Invoicing\Models\Invoice;
use Aero\Invoicing\Models\Issuer;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    protected function issuer(int $tenant = 1, string $nit = '1020304050', string $driver = 'simulated'): Issuer
    {
        return Issuer::create([
            'tenant_id' => $tenant, 'name' => "Emisor {$tenant}", 'nit' => $nit, 'legal_name' => 'Empresa SRL',
            'driver' => $driver, 'is_active' => true,
        ]);
    }

    protected function data(array $over = []): array
    {
        return $over + [
            'buyer_nit' => '1234567011', 'buyer_name' => 'Cliente SRL',
            'items' => [['description' => 'Consultoría', 'quantity' => 1, 'unit_price' => 113, 'sin_product_code' => '99100', 'economic_activity' => '620100']],
        ];
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_invoicing_issuers', 'aero_invoicing_cufds', 'aero_invoicing_invoices'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testPermissionsAndNavigationAreCoherent(): void
    {
        $plugin = new \Aero\Invoicing\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        $this->assertContains('aero.invoicing.use', $declared);
        $this->assertContains('aero.invoicing.superadmin', $declared);

        foreach ($plugin->registerNavigation() as $item) {
            $required = $item['permissions'] ?? [];
            foreach (array_values($item['sideMenu'] ?? []) as $side) {
                $required = array_merge($required, $side['permissions'] ?? []);
            }

            foreach ($required as $p) {
                $this->assertContains($p, $declared, "El menú exige un permiso no declarado: {$p}");
            }
        }
    }

    public function testIvaIsIncludedInUnitPrice(): void
    {
        $totals = Invoice::totals([['quantity' => 1, 'unit_price' => 113]]);
        $this->assertSame(113.0, $totals['total']);
        $this->assertSame(13.0, $totals['iva']);

        $inv = InvoiceService::create($this->issuer(), $this->data());
        $this->assertSame(113.0, $inv->total);
        $this->assertSame(13.0, $inv->iva);
        $this->assertSame('draft', $inv->status);
    }

    public function testInvoiceNeedsItemsWithSinCodes(): void
    {
        $this->expectException(\October\Rain\Exception\ValidationException::class);
        InvoiceService::create($this->issuer(), $this->data(['items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 10]]]));
    }

    public function testCreateIsIdempotentByReference(): void
    {
        $issuer = $this->issuer();
        $a = InvoiceService::create($issuer, $this->data(['reference' => 'ORD-1']));
        $b = InvoiceService::create($issuer, $this->data(['reference' => 'ORD-1']));

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Invoice::count());

        // Otro tenant puede reutilizar la misma referencia.
        $c = InvoiceService::create($this->issuer(2, '555555555'), $this->data(['reference' => 'ORD-1']));
        $this->assertNotSame($a->id, $c->id);
    }

    public function testIssueWithSimulatedDriverValidatesAndNumbers(): void
    {
        $issuer = $this->issuer();
        $inv = InvoiceService::issue(InvoiceService::create($issuer, $this->data()));

        $this->assertSame('validated', $inv->status);
        $this->assertSame('1', $inv->number);
        $this->assertNotEmpty($inv->cuf);
        $this->assertNotNull($inv->issued_at);

        $second = InvoiceService::issue(InvoiceService::create($issuer, $this->data()));
        $this->assertSame('2', $second->number);
    }

    public function testRejectedInvoiceKeepsNoNumberAndCanBeCorrected(): void
    {
        $inv = InvoiceService::create($this->issuer(), $this->data(['buyer_nit' => SimulatedDriver::REJECTED_NIT]));
        $inv = InvoiceService::issue($inv);

        $this->assertSame('rejected', $inv->status);
        $this->assertNull($inv->number);
        $this->assertNull($inv->issued_at);
    }

    public function testAlreadyIssuedInvoiceIsNotReissued(): void
    {
        $inv = InvoiceService::issue(InvoiceService::create($this->issuer(), $this->data()));

        $this->expectException(\DomainException::class);
        InvoiceService::issue($inv);
    }

    public function testVoidOnlyIssuedInvoices(): void
    {
        $draft = InvoiceService::create($this->issuer(), $this->data());
        try {
            InvoiceService::void($draft);
            $this->fail('Un borrador no se anula.');
        } catch (\DomainException) {
            $this->assertSame('draft', $draft->fresh()->status);
        }

        $issued = InvoiceService::issue(InvoiceService::create($this->issuer(1, '777777777'), $this->data()));
        $voided = InvoiceService::void($issued, 1);
        $this->assertSame('voided', $voided->status);
        $this->assertSame(1, $voided->void_reason_code);
    }

    public function testCufdIsReusedWithinItsValidity(): void
    {
        $issuer = $this->issuer();
        $a = CufdManager::current($issuer);
        $b = CufdManager::current($issuer);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Cufd::count());

        // Una CUFD a punto de vencer se renueva.
        $a->update(['valid_until' => now()->addMinutes(2)]);
        $this->assertNotSame($a->id, CufdManager::current($issuer)->id);
    }

    public function testProviderFailureLeavesInvoiceRetryableWithoutNumber(): void
    {
        DriverFactory::override('simulated', new class implements InvoicingDriver {
            public function code(): string { return 'simulated'; }
            public function obtainCufd(Issuer $i): array { throw new DriverException('sin conexión'); }
            public function emit(Issuer $i, Invoice $v, Cufd $c): array { throw new DriverException('x'); }
            public function void(Issuer $i, Invoice $v, Cufd $c, int $r): array { throw new DriverException('x'); }
        });

        try {
            $inv = InvoiceService::issue(InvoiceService::create($this->issuer(), $this->data()));
            $this->assertSame('error', $inv->status);
            $this->assertStringContainsString('sin conexión', $inv->error);
            $this->assertNull($inv->number);
            $this->assertTrue($inv->isEditable, 'Un error debe poder reintentarse.');
        } finally {
            DriverFactory::override('simulated', null);
        }
    }

    public function testFloogosIssuerWithoutCredentialIsNotReady(): void
    {
        $issuer = $this->issuer(1, '888888888', 'floogos');
        $this->assertFalse($issuer->isReady);

        $inv = InvoiceService::create($issuer, $this->data());
        $this->expectException(\DomainException::class);
        InvoiceService::issue($inv);
    }

    public function testTenantScopeFailsClosed(): void
    {
        InvoiceService::create($this->issuer(1), $this->data());
        InvoiceService::create($this->issuer(2, '222222222'), $this->data());

        $this->assertSame(1, Invoice::forTenant(1)->count());
        $this->assertSame(1, Invoice::forTenant(2)->count());
        $this->assertSame(0, Invoice::forTenant(null)->count(), 'Sin tenant no debe verse nada.');
        $this->assertSame(0, Issuer::forTenant(null)->count());
        $this->assertSame(0, Cufd::forTenant(null)->count());
    }
}
