<?php namespace Aero\Sites\Tests;

use Aero\Sites\Models\Tenant;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use PluginTestCase;

class ScopeQueryToTenantTest extends PluginTestCase
{
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    protected function scoper(?Tenant $tenant): object
    {
        return new class($tenant) {
            use ResolvesCurrentTenant;

            public function __construct(?Tenant $t)
            {
                $this->currentTenant = $t;
            }

            protected function getCurrentTenant(): ?Tenant
            {
                return $this->currentTenant;
            }

            public function scope($q)
            {
                return $this->scopeQueryToTenant($q);
            }
        };
    }

    public function testSinTenantYSinSuperadminNoDevuelveNada(): void
    {
        $sql = $this->scoper(null)->scope(\DB::table('t'))->toSql();

        $this->assertStringContainsString('1 = 0', $sql);
    }

    public function testConTenantFiltraPorEse(): void
    {
        $t = new Tenant;
        $t->id = 5;
        $q = $this->scoper($t)->scope(\DB::table('t'));

        $this->assertStringContainsString('tenant_id', $q->toSql());
        $this->assertSame([5], $q->getBindings());
    }
}
