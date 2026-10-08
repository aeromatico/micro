<?php namespace Aero\Sites\Tests;

use Aero\Sites\Classes\Workflows\PlanNodes;
use Illuminate\Support\Facades\DB;
use PluginTestCase;

class PlanNodesTest extends PluginTestCase
{
    /** Solo las tablas que lee el nodo; no se arranca Sites (depende de RainLab.User, dominios, etc.). */
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    public function setUp(): void
    {
        parent::setUp();

        \Schema::create('aero_sites_plans', function ($t) {
            $t->id();
            $t->string('code')->nullable();
            $t->string('name');
            $t->decimal('price', 10, 2)->default(0);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        \Schema::create('aero_sites_tenants', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('handle')->nullable();
            $t->string('status')->default('active');
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('billing_period')->nullable();
            $t->timestamp('plan_expires_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });

        \Schema::create('aero_sites_plan_renewals', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('plan_id');
            $t->string('period', 16);
            $t->timestamp('cycle_due_at');
            $t->string('status', 16)->default('pending');
            $t->unsignedBigInteger('qr_code_id')->nullable();
            $t->string('payment_reference')->nullable();
            $t->decimal('amount', 10, 2);
            $t->string('currency', 8)->default('BOB');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        \Schema::create('aero_pay_qr_codes', function ($t) {
            $t->id();
            $t->uuid('internal_reference');
            $t->longText('qr_image')->nullable();
            $t->timestamps();
        });

        DB::table('aero_sites_plans')->insert(['id' => 1, 'name' => 'Pro', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tenant(int $id, ?\DateTimeInterface $expires, string $status = 'active', ?int $plan = 1): void
    {
        DB::table('aero_sites_tenants')->insert([
            'id' => $id, 'name' => "T{$id}", 'handle' => "t{$id}", 'status' => $status, 'plan_id' => $plan,
            'billing_period' => 'monthly', 'plan_expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function testDefinitionIsCallableAndDeclaresEveryHandle(): void
    {
        $def = PlanNodes::definitions()['sites.plan_status'];

        $this->assertTrue(is_callable($def['handler']));
        $this->assertSame(['active', 'expiring', 'overdue', 'no_plan'], array_column($def['handles'], 'id'));
    }

    public function testRoutesByDaysLeftAndStatus(): void
    {
        $this->tenant(1, now()->addDays(30));
        $this->tenant(2, now()->addDays(3));
        $this->tenant(3, now()->subDays(2));
        $this->tenant(4, now()->addDays(30), 'suspended');
        $this->tenant(5, null);

        $run = fn (int $id) => PlanNodes::status([], [], $id);

        $this->assertSame('active', $run(1)['handle']);
        $this->assertSame('expiring', $run(2)['handle']);
        $this->assertSame(3, $run(2)['output']['days_left']);
        $this->assertSame('overdue', $run(3)['handle']);
        $this->assertSame('overdue', $run(4)['handle']);
        $this->assertSame('active', $run(5)['handle'], 'sin vencimiento (plan sin ciclo) se considera vigente');
        $this->assertNull($run(5)['output']['days_left']);
        $this->assertSame('Pro', $run(1)['output']['plan_name']);
        $this->assertSame('plan', $run(1)['var']);
    }

    public function testTenantWithoutPlanOrUnknownTenantHasNoPlan(): void
    {
        $this->tenant(1, null, 'active', null);

        $this->assertSame('no_plan', PlanNodes::status([], [], 1)['handle']);
        $this->assertSame('no_plan', PlanNodes::status([], [], 99)['handle']);
    }

    public function testShowsThePendingRenewalOfThatTenantWithItsQr(): void
    {
        $this->tenant(1, now()->addDays(4));
        $this->tenant(2, now()->addDays(4));

        $qr = DB::table('aero_pay_qr_codes')->insertGetId([
            'internal_reference' => 'aaaaaaaa-0000-0000-0000-000000000001', 'qr_image' => base64_encode('png'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('aero_sites_plan_renewals')->insert([
            ['tenant_id' => 1, 'plan_id' => 1, 'period' => 'monthly', 'cycle_due_at' => now()->addDays(4), 'status' => 'pending',
                'qr_code_id' => $qr, 'payment_reference' => 'ref-1', 'amount' => 199, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => 2, 'plan_id' => 1, 'period' => 'monthly', 'cycle_due_at' => now()->addDays(4), 'status' => 'paid',
                'qr_code_id' => null, 'payment_reference' => 'ref-2', 'amount' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $one = PlanNodes::status([], [], 1);
        $this->assertTrue($one['output']['renewal_pending']);
        $this->assertSame(199.0, $one['output']['renewal_amount']);
        $this->assertStringContainsString('/api/v1/pay/public/qr/aaaaaaaa-0000-0000-0000-000000000001/image', $one['output']['renewal_image_url']);
        $this->assertStringContainsString('vence en', $one['output']['text']);
        $this->assertStringContainsString('Renovación pendiente: 199.00 BOB', $one['output']['text']);

        // El tenant 2 ya pagó: no ve el cobro del 1 ni uno suyo ya pagado.
        $this->assertFalse(PlanNodes::status([], [], 2)['output']['renewal_pending']);
    }

    public function testRefusesToRunWithoutATenant(): void
    {
        $this->expectException(\RuntimeException::class);
        PlanNodes::status([], [], null);
    }
}
