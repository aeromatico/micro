<?php namespace Aero\Chatbots\Tests;

use Aero\Chatbots\Classes\TenantGuard;
use PHPUnit\Framework\TestCase;

/**
 * Un bot de un cliente nunca atiende la cuenta de otro cliente: sería leer los
 * datos de uno y contestar desde el número de otro. (Sin arrancar la app: la
 * regla no tiene dependencias.)
 */
class BotTenantTest extends TestCase
{
    public function testBotOfATenantOnlyServesAccountsOfThatTenant(): void
    {
        $this->assertTrue(TenantGuard::matches(25, 25));
        $this->assertFalse(TenantGuard::matches(2, 25), 'el caso real: el bot del tenant 2 sobre la cuenta del 25');
        $this->assertFalse(TenantGuard::matches(25, null), 'una cuenta sin cliente tampoco es de este bot');
    }

    public function testPlatformBotsWithoutTenantCanServeAnyAccount(): void
    {
        $this->assertTrue(TenantGuard::matches(null, 25));
        $this->assertTrue(TenantGuard::matches(null, null));
    }
}
