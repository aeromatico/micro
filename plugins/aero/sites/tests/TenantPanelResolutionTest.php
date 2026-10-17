<?php namespace Aero\Sites\Tests;

use Aero\Sites\Models\Tenant;
use Backend\Models\User;
use PluginTestCase;

class TenantPanelResolutionTest extends PluginTestCase
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
            $t->string('name')->nullable();
            $t->string('handle')->nullable();
            $t->string('status')->default('active');
            $t->unsignedBigInteger('backend_user_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        \Schema::create('aero_sites_tenant_users', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('role')->nullable();
            $t->timestamps();
        });

        \DB::table('aero_sites_root_domains')->insert(['domain' => 'market.test', 'is_active' => 1]);
        // 1 = ajeno, 2 = propio de Ana, 3 = donde Ana es miembro
        foreach ([[1, 'Zeta', 'zeta', 99], [2, 'Propio', 'propio', 7], [3, 'Alfa', 'alfa', 98]] as [$id, $n, $h, $owner]) {
            \DB::table('aero_sites_tenants')->insert(['id' => $id, 'name' => $n, 'handle' => $h, 'backend_user_id' => $owner]);
        }
        \DB::table('aero_sites_tenant_users')->insert(['tenant_id' => 3, 'user_id' => 7]);
    }

    protected function ana(): User
    {
        $u = new User;
        $u->id = 7;
        return $u;
    }

    public function testElPropioVaPrimeroYElResultadoNoIncluyeAjenos(): void
    {
        $ids = Tenant::accessibleFor($this->ana())->pluck('id')->all();

        $this->assertSame([2, 3], $ids);
    }

    public function testSinEleccionUsaElPropio(): void
    {
        $this->assertSame(2, Tenant::resolveForBackendUser($this->ana(), 'panel.market.test')->id);
    }

    public function testEleccionPorSesionYPorQueryYNoAjenos(): void
    {
        session(['aero_sites_active_tenant' => 3]);
        $this->assertSame(3, Tenant::resolveForBackendUser($this->ana(), 'panel.market.test')->id);

        session(['aero_sites_active_tenant' => 1]); // ajeno: se ignora
        $this->assertSame(2, Tenant::resolveForBackendUser($this->ana(), 'panel.market.test')->id);

        request()->query->set('tenant', 'alfa');
        $this->assertSame(3, Tenant::resolveForBackendUser($this->ana(), 'panel.market.test')->id);
        $this->assertSame(3, session('aero_sites_active_tenant'));

        request()->query->set('tenant', 'zeta'); // ajeno por query: se ignora
        session()->forget('aero_sites_active_tenant');
        $this->assertSame(2, Tenant::resolveForBackendUser($this->ana(), 'panel.market.test')->id);
    }

    public function testSubdominioManda(): void
    {
        $this->assertSame(3, Tenant::resolveForBackendUser($this->ana(), 'alfa.market.test')->id);
        Tenant::resolveForBackendUser($this->ana(), 'zeta.market.test', $denied);
        $this->assertTrue($denied);
    }
}
