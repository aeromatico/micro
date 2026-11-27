<?php namespace Aero\Sites\Tests;

use Aero\Sites\Classes\SignupReservations;
use Aero\Sites\Components\SignupWizard;
use Aero\Sites\Models\Tenant;
use PluginTestCase;

class SignupReservationTest extends PluginTestCase
{
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    public function setUp(): void
    {
        parent::setUp();

        \Schema::create('aero_sites_root_domains', function ($t) {
            $t->id();
            $t->string('domain');
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        \Schema::create('aero_sites_tenants', function ($t) {
            $t->id();
            $t->string('handle')->nullable();
            $t->string('status')->default('active');
            $t->unsignedBigInteger('root_domain_id')->nullable();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('billing_period')->nullable();
            $t->string('signup_domain')->nullable();
            $t->string('signup_domain_source')->nullable();
            $t->unsignedBigInteger('signup_qr_code_id')->nullable();
            $t->string('signup_payment_reference')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        \Schema::create('aero_pay_qr_codes', function ($t) {
            $t->id();
            $t->string('status');
            $t->decimal('amount', 14, 2);
            $t->timestamps();
        });

        \DB::table('aero_sites_root_domains')->insert(['id' => 1, 'domain' => 'market.test', 'is_active' => 1]);
        \DB::table('aero_pay_qr_codes')->insert(['id' => 5, 'status' => 'pending', 'amount' => 99]);
        \DB::table('aero_sites_tenants')->insert([
            ['id' => 1, 'handle' => 'reservado', 'status' => 'pending_payment', 'root_domain_id' => 1, 'plan_id' => 2, 'billing_period' => 'monthly', 'signup_qr_code_id' => 5, 'signup_payment_reference' => 'ref-1'],
            ['id' => 2, 'handle' => 'activo', 'status' => 'active', 'root_domain_id' => 1, 'plan_id' => 2, 'billing_period' => 'monthly', 'signup_qr_code_id' => null, 'signup_payment_reference' => 'ref-2'],
        ]);
    }

    protected function check(string $handle): array
    {
        request()->setMethod('POST');
        request()->request->set('handle', $handle);

        return (new SignupWizard)->onCheckSubdomain();
    }

    public function testLaReservaPropiaSigueDisponible(): void
    {
        SignupReservations::remember(Tenant::find(1));

        $this->assertTrue($this->check('reservado')['available']);
    }

    public function testLaReservaAjenaSigueBloqueada(): void
    {
        $result = $this->check('reservado');

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('reservado mientras', $result['message']);
    }

    public function testUnaCuentaActivaNuncaSeReutiliza(): void
    {
        SignupReservations::remember(Tenant::find(2));

        $this->assertFalse($this->check('activo')['available']);
    }

    public function testElQrSoloSeConservaSiElCobroNoCambia(): void
    {
        $reusable = new \ReflectionMethod(SignupWizard::class, 'reusableQr');
        $reusable->setAccessible(true);
        $call = fn (...$args) => $reusable->invoke(new SignupWizard, Tenant::find(1), ...$args);

        $this->assertSame(5, $call(2, 'monthly', 99.0, null, null)?->id);
        $this->assertNull($call(1, 'monthly', 99.0, null, null), 'otro plan');
        $this->assertNull($call(2, 'annual', 99.0, null, null), 'otro periodo');
        $this->assertNull($call(2, 'monthly', 198.0, null, null), 'otro monto');
        $this->assertNull($call(2, 'monthly', 99.0, 'mio.com', 'existing'), 'otro dominio');

        \DB::table('aero_pay_qr_codes')->where('id', 5)->update(['status' => 'cancelled']);
        $this->assertNull($call(2, 'monthly', 99.0, null, null), 'QR anulado');
    }

    public function testNoLiberaUnaReservaPagada(): void
    {
        \DB::table('aero_pay_qr_codes')->where('id', 5)->update(['status' => 'paid']);

        $this->assertFalse(SignupReservations::release(Tenant::find(1)));
        $this->assertSame(1, \DB::table('aero_sites_tenants')->where('id', 1)->count());
    }

    public function testElEnlaceAlPanelEsCompletoYDelDominioRaiz(): void
    {
        $this->assertSame('https://market.test/backend?tenant=activo', Tenant::find(2)->publicBackendUrl());
    }
}
