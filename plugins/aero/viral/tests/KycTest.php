<?php namespace Aero\Viral\Tests;

use Aero\Viral\Classes\Kyc\DiditClient;
use Aero\Viral\Classes\Kyc\DiditWebhook;
use Aero\Viral\Classes\Kyc\KycException;
use Aero\Viral\Classes\Kyc\KycService;
use Aero\Viral\Models\Application;
use Aero\Viral\Models\KycCheck;
use Illuminate\Support\Facades\Http;
use PluginTestCase;

class KycTest extends PluginTestCase
{
    protected const SECRET = 'whsec_test';

    public function setUp(): void
    {
        parent::setUp();
        config(['services.didit' => ['api_key' => 'k', 'workflow_id' => 'wf-1', 'webhook_secret' => self::SECRET, 'base_url' => 'https://verification.didit.me']]);
    }

    protected function app1(): Application
    {
        return Application::create(['user_id' => 1, 'track' => 'shorts', 'level' => 1]);
    }

    protected function signedHeaders(array $payload, ?int $ts = null, string $secret = self::SECRET): array
    {
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ts = $ts ?? time();

        return [$raw, [
            'x-signature' => hash_hmac('sha256', $raw, $secret),
            'x-signature-v2' => hash_hmac('sha256', DiditWebhook::canonical($payload), $secret),
            'x-timestamp' => (string) $ts,
        ]];
    }

    public function testSignatureAcceptsRawAndV2AndRejectsTamperingStaleOrWrongSecret(): void
    {
        $payload = ['webhook_type' => 'status.updated', 'status' => 'Approved', 'session_id' => 's1', 'metadata' => ['b' => 1, 'a' => 'ñandú']];
        [$raw, $h] = $this->signedHeaders($payload);

        $this->assertTrue(DiditWebhook::verify($raw, $h, self::SECRET));
        $this->assertTrue(DiditWebhook::verify($raw, ['x-signature-v2' => $h['x-signature-v2'], 'x-timestamp' => $h['x-timestamp']], self::SECRET));
        $this->assertFalse(DiditWebhook::verify(str_replace('Approved', 'Declined', $raw), $h, self::SECRET));
        $this->assertFalse(DiditWebhook::verify($raw, $h, 'otro-secreto'));
        $this->assertFalse(DiditWebhook::verify($raw, $h, ''));
        [$old, $hOld] = $this->signedHeaders($payload, time() - 4000);
        $this->assertFalse(DiditWebhook::verify($old, $hOld, self::SECRET));
        $this->assertFalse(DiditWebhook::verify($raw, ['x-timestamp' => $h['x-timestamp']], self::SECRET));
    }

    public function testCanonicalSortsKeysRecursivelyAndKeepsUnicode(): void
    {
        $this->assertSame('{"a":{"x":1,"y":2},"b":[{"c":1,"d":2}],"z":"ñ"}', DiditWebhook::canonical(['z' => 'ñ', 'b' => [['d' => 2, 'c' => 1]], 'a' => ['y' => 2, 'x' => 1]]));
    }

    public function testStatusMapping(): void
    {
        $this->assertSame('verified', DiditWebhook::mapStatus('Approved'));
        $this->assertSame('rejected', DiditWebhook::mapStatus('Declined'));
        foreach (['Expired', 'Abandoned', 'Kyc Expired'] as $s) {
            $this->assertSame('expired', DiditWebhook::mapStatus($s));
        }
        foreach (['In Review', 'In Progress', 'Not Started', 'Awaiting User', 'Resubmitted'] as $s) {
            $this->assertSame('pending', DiditWebhook::mapStatus($s));
        }
    }

    public function testStartCreatesSessionAndMarksPending(): void
    {
        Http::fake(['verification.didit.me/v3/session/' => Http::response(['session_id' => 'sess-1', 'url' => 'https://verify.didit.me/es/session/abc', 'status' => 'Not Started'], 201)]);
        $app = $this->app1();

        $url = (new KycService)->start($app, 'https://viral.market.com.bo/aplicar?kyc=1');

        $this->assertSame('https://verify.didit.me/es/session/abc', $url);
        $this->assertSame('pending', $app->fresh()->kyc_status);
        $check = KycCheck::first();
        $this->assertSame('sess-1', $check->external_ref);
        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'k') && $r['workflow_id'] === 'wf-1' && $r['vendor_data'] === $app->uuid && $r['language'] === 'es');
        // reintento: reutiliza la misma verificación, no duplica
        (new KycService)->start($app->fresh(), 'https://viral.market.com.bo/aplicar?kyc=1');
        $this->assertSame(1, KycCheck::count());
    }

    public function testStartFailsClearlyWhenNotConfiguredOrApiRejects(): void
    {
        config(['services.didit.workflow_id' => null]);
        $this->assertFalse(KycService::isAvailable());
        try { (new KycService)->start($this->app1(), 'x'); $this->fail(); } catch (KycException $e) { $this->assertStringContainsString('aún no está disponible', $e->getMessage()); }

        config(['services.didit.workflow_id' => 'wf-1']);
        Http::fake(['verification.didit.me/v3/session/' => Http::response(['detail' => 'This API key has no write access to sessions.'], 403)]);
        $this->expectException(KycException::class);
        (new KycService)->start($this->app1(), 'x');
    }

    public function testApprovedWebhookVerifiesAndUnlocksApproval_IdempotentAndNeverDowngrades(): void
    {
        Http::fake(['verification.didit.me/v3/session/' => Http::response(['session_id' => 'sess-2', 'url' => 'https://v/x', 'status' => 'Not Started'], 201)]);
        $app = $this->app1();
        $svc = new KycService;
        $svc->start($app, 'x');

        $evt = ['event_id' => 'e1', 'webhook_type' => 'status.updated', 'session_id' => 'sess-2', 'status' => 'Approved',
            'decision' => ['id_verifications' => [['document_number' => '1234567 LP']]]];
        $this->assertTrue($svc->handleWebhook($evt));
        $this->assertFalse($svc->handleWebhook($evt));   // mismo event_id: ignorado

        $app = $app->fresh();
        $this->assertSame('verified', $app->kyc_status);
        $this->assertSame('1234567 LP', KycCheck::first()->doc_number);
        $app->approve(1);                                // ahora sí se puede aprobar
        $this->assertSame('approved', $app->fresh()->status);

        $this->assertFalse($svc->handleWebhook(['event_id' => 'e2', 'webhook_type' => 'status.updated', 'session_id' => 'sess-2', 'status' => 'Expired']));
        $this->assertSame('verified', $app->fresh()->kyc_status);
    }

    public function testDeclinedAndUnknownSessions(): void
    {
        Http::fake(['verification.didit.me/v3/session/' => Http::response(['session_id' => 'sess-3', 'url' => 'https://v/x', 'status' => 'Not Started'], 201)]);
        $app = $this->app1();
        $svc = new KycService;
        $svc->start($app, 'x');

        $this->assertFalse($svc->handleWebhook(['event_id' => 'x1', 'webhook_type' => 'status.updated', 'session_id' => 'desconocida', 'status' => 'Approved']));
        $this->assertTrue($svc->handleWebhook(['event_id' => 'x2', 'webhook_type' => 'status.updated', 'session_id' => 'sess-3', 'status' => 'Declined',
            'decision' => ['id_verifications' => [['warnings' => [['short_description' => 'Documento ilegible']]]]]]));
        $this->assertSame('rejected', $app->fresh()->kyc_status);
        $this->assertSame('Documento ilegible', KycCheck::first()->reject_reason);
        $this->expectException(\RuntimeException::class);
        $app->fresh()->approve(1);
    }
}
