<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\PhoneVerifier;
use October\Rain\Exception\ApplicationException;
use PluginTestCase;

class PhoneTest extends PluginTestCase
{
    protected array $sent = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->sent = [];
        PhoneVerifier::$sender = function ($to, $body) { $this->sent[] = [$to, $body]; };
        \Cache::flush();
        \Illuminate\Support\Facades\RateLimiter::clear('viral-otp-phone:+59171230001');
    }

    public function tearDown(): void
    {
        PhoneVerifier::$sender = null;
        parent::tearDown();
    }

    protected function code(): string
    {
        preg_match('/es (\d{6})$/', $this->sent[count($this->sent) - 1][1], $m);

        return $m[1];
    }

    public function testSendAndVerify(): void
    {
        PhoneVerifier::send('7 123 0001', '9.9.9.1');
        $this->assertSame('59171230001', $this->sent[0][0]);
        $this->assertNull(PhoneVerifier::verifiedPhone());
        $this->assertTrue(PhoneVerifier::check('71230001', $this->code()));
        $this->assertSame('+59171230001', PhoneVerifier::verifiedPhone());
    }

    public function testWrongCodeLocksAfterFiveTries(): void
    {
        PhoneVerifier::send('71230001', '9.9.9.2');
        $good = $this->code();
        for ($i = 0; $i < 5; $i++) {
            try { PhoneVerifier::check('71230001', $good === '000000' ? '111111' : '000000'); $this->fail(); } catch (ApplicationException $e) { $this->assertStringContainsString('no es correcto', $e->getMessage()); }
        }
        $this->expectExceptionMessage('Demasiados intentos');
        PhoneVerifier::check('71230001', $good);
    }

    public function testCooldownAndPhoneLimit(): void
    {
        PhoneVerifier::send('71230001', '9.9.9.3');
        try { PhoneVerifier::send('71230001', '9.9.9.3'); $this->fail(); } catch (ApplicationException $e) { $this->assertStringContainsString('minuto', $e->getMessage()); }
        $this->assertCount(1, $this->sent);
    }

    public function testInvalidPhoneAndDeliveryFailure(): void
    {
        try { PhoneVerifier::send('51234567', '9.9.9.4'); $this->fail(); } catch (ApplicationException $e) { $this->assertStringContainsString('celular', $e->getMessage()); }
        PhoneVerifier::$sender = function () { throw new \RuntimeException('Zernio caído'); };
        try { PhoneVerifier::send('71230001', '9.9.9.5'); $this->fail(); } catch (ApplicationException $e) { $this->assertStringContainsString('No pudimos enviar', $e->getMessage()); }
        // tras un fallo no queda enfriamiento ni código: se puede reintentar
        PhoneVerifier::$sender = function ($to, $body) { $this->sent[] = [$to, $body]; };
        PhoneVerifier::send('71230001', '9.9.9.5');
        $this->assertCount(1, $this->sent);
    }

    public function testCodeForAnotherPhoneIsRejected(): void
    {
        PhoneVerifier::send('71230001', '9.9.9.6');
        $this->expectExceptionMessage('venció');
        PhoneVerifier::check('71230002', $this->code());
    }
}
