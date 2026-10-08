<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\AgentChat;
use Aero\Workspaces\Classes\AgentRunner;
use Aero\Workspaces\Classes\Billing;
use Aero\Workspaces\Classes\Hiring;
use Aero\Workspaces\Classes\Tasks;
use Aero\Workspaces\Models\Hire;
use Aero\Workspaces\Models\Message;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\TaskRate;
use PluginTestCase;

require_once __DIR__ . '/FakeLlm.php';

/**
 * Cobro de Workspaces: contratación, mensajes reales y encargos, con saldo
 * insuficiente, reembolso al cancelar, despedir y el reporte del superadmin.
 */
class BillingTest extends PluginTestCase
{
    protected FakeLedger $ledger;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\Aero\Credits\Classes\Credits::class)) {
            $this->markTestSkipped('Aero.Credits no está instalado.');
        }

        // Credits real usa triggers de MySQL; aquí se prueba la lógica de Workspaces con un libro falso.
        $this->ledger = Billing::$ledger = new FakeLedger();
        Settings::set('charge_enabled', true);
        Settings::set('credit_type', 'azul');
    }

    public function tearDown(): void
    {
        Billing::$ledger = null;
        Settings::set('charge_enabled', false); // el caché de ajustes sobrevive entre pruebas
        parent::tearDown();
    }

    protected function agent(string $slug, array $extra = [], int $hire = 0, int $turn = 0, int $task = 0): Staff
    {
        $s = new Staff();
        $s->fill($extra + ['name' => ucfirst($slug), 'slug' => $slug, 'role' => 'Rol', 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'contenido', 'is_active' => true,
            'system_prompt' => 'Eres ' . $slug, 'hire_fee' => $hire]);
        $s->save();
        TaskRate::create(['staff_id' => $s->id, 'task_type' => 'chat_turn', 'fee' => $turn]);
        TaskRate::create(['staff_id' => $s->id, 'task_type' => 'encargo', 'fee' => $task]);

        $skill = Skill::create(['kind' => 'official', 'name' => 'Hace ' . $slug, 'slug' => 'hace-' . $slug, 'description' => 'Úsala.', 'tools' => ['workspaces_team']]);
        $s->skills()->attach($skill->id);

        return $s->fresh(['skills', 'taskRateRows']);
    }

    public function testHiringChargesAndStoresTheTransaction(): void
    {
        $this->agent('jefa', ['is_orchestrator' => true]);
        $ana = $this->agent('ana', [], hire: 50);
        $this->ledger->fund(1, 80);

        $out = Hiring::hire(1, 'ana');

        $this->assertSame(50, $out['charged']);
        $this->assertSame(30, $this->ledger->balance(1));
        $this->assertNotNull(Hire::first()->credit_transaction_id);

        $this->expectException(\DomainException::class);
        Hiring::hire(1, 'ana');
    }

    public function testHiringWithoutBalanceFailsAndHiresNothing(): void
    {
        $this->agent('ana', [], hire: 50);
        $this->ledger->fund(1, 10);

        try {
            Hiring::hire(1, 'ana');
            $this->fail('debió fallar por saldo');
        }
        catch (\DomainException $e) {
            $this->assertStringContainsString('puntos suficientes', $e->getMessage());
        }

        $this->assertSame(0, Hire::count());
        $this->assertSame(10, $this->ledger->balance(1));
    }

    public function testDismissThenRehireChargesAgain(): void
    {
        $ana = $this->agent('ana', [], hire: 20);
        $this->ledger->fund(1, 100);

        Hiring::hire(1, 'ana');
        Hiring::dismiss(1, 'ana');
        $this->assertSame(0, Hire::count());
        $this->assertSame(80, $this->ledger->balance(1), 'despedir no reembolsa');

        $this->travel(2)->seconds();
        Hiring::hire(1, 'ana');
        $this->assertSame(60, $this->ledger->balance(1), 'recontratar cobra de nuevo');
    }

    public function testCannotDismissTheOrchestratorOrAStranger(): void
    {
        $this->agent('jefa', ['is_orchestrator' => true]);
        $this->agent('ana');

        foreach (['jefa', 'ana'] as $slug) {
            try {
                Hiring::dismiss(1, $slug);
                $this->fail("no debió permitir despedir a {$slug}");
            }
            catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRealTurnIsChargedOnlyWhenItSucceeds(): void
    {
        $ana = $this->agent('ana', [], turn: 3);
        $this->ledger->fund(1, 10);

        $ok = Message::create(['tenant_id' => 1, 'staff_id' => $ana->id, 'role' => 'assistant', 'status' => 'pending']);
        AgentRunner::run($ok, new FakeLlm([['text' => 'Hecho']]));
        $this->assertSame(7, $this->ledger->balance(1));
        $this->assertSame(3, (int) $ok->fresh()->charged_points);

        $bad = Message::create(['tenant_id' => 1, 'staff_id' => $ana->id, 'role' => 'assistant', 'status' => 'pending']);
        AgentRunner::run($bad, new FakeLlm([['text' => '']]));
        $this->assertSame('error', $bad->fresh()->status);
        $this->assertSame(7, $this->ledger->balance(1), 'un turno fallido no se cobra');

        // Repetir el cobro del mismo mensaje no cobra dos veces.
        Billing::chargeTurn($ok->fresh(), $ana);
        $this->assertSame(7, $this->ledger->balance(1));
    }

    public function testChatIsBlockedBeforeWorkingWhenBalanceIsShort(): void
    {
        $ana = $this->agent('ana', [], turn: 5);
        Hire::create(['tenant_id' => 1, 'staff_id' => $ana->id, 'fee_charged' => 0, 'hired_at' => now()]);
        $this->ledger->fund(1, 2);

        try {
            AgentChat::send(1, null, 'ana', 'Hola', false);
            $this->fail('debió bloquear por saldo');
        }
        catch (\DomainException $e) {
            $this->assertStringContainsString('5 pts por mensaje', $e->getMessage());
        }

        $this->assertSame(0, Message::count(), 'no se guarda nada ni se gasta IA');
    }

    public function testNothingIsChargedWithChargingOff(): void
    {
        Settings::set('charge_enabled', false);
        $ana = $this->agent('ana', [], hire: 50, turn: 3);

        $this->assertSame(0, Billing::turnCost($ana));
        $reply = Message::create(['tenant_id' => 1, 'staff_id' => $ana->id, 'role' => 'assistant', 'status' => 'pending']);
        AgentRunner::run($reply, new FakeLlm([['text' => 'Hecho']]));

        $this->assertSame(0, (int) $reply->fresh()->charged_points);
        $this->assertSame(0, Hiring::hire(1, 'ana')['charged']);
    }

    public function testCancellingATaskRefundsIt(): void
    {
        $this->agent('jefa', ['is_orchestrator' => true], task: 40);
        $this->ledger->fund(1, 100);

        $task = Tasks::submit(1, 'Hazme una campaña');
        $charged = (int) $task->charged_points;
        $this->assertGreaterThan(0, $charged);
        $this->assertSame(100 - $charged, $this->ledger->balance(1));

        $out = Tasks::cancel(1, $task->id);

        $this->assertSame('cancelled', $out['status']);
        $this->assertTrue($out['refunded']);
        $this->assertSame(100, $this->ledger->balance(1));

        $this->expectException(\DomainException::class);
        Tasks::cancel(1, $task->id);
    }

    public function testCannotCancelAnotherTenantsTask(): void
    {
        $this->agent('jefa', ['is_orchestrator' => true], task: 40);
        $this->ledger->fund(1, 100);
        $task = Tasks::submit(1, 'Hazme una campaña');

        $this->expectException(\DomainException::class);
        Tasks::cancel(2, $task->id);
    }

    public function testReportAddsUpByConceptAgentAndTenant(): void
    {
        $this->agent('jefa', ['is_orchestrator' => true], task: 40);
        $ana = $this->agent('ana', [], hire: 50, turn: 2);
        $this->ledger->fund(1, 500);

        Hiring::hire(1, 'ana');
        $reply = Message::create(['tenant_id' => 1, 'staff_id' => $ana->id, 'role' => 'assistant', 'status' => 'pending']);
        AgentRunner::run($reply, new FakeLlm([['text' => 'Hecho']]));
        $task = Tasks::submit(1, 'Algo corto');
        Tasks::cancel(1, $task->id);

        $r = Billing::report(30);

        $this->assertSame(50, $r['totals']['hire']);
        $this->assertSame(2, $r['totals']['turn']);
        $this->assertSame((int) $task->charged_points, $r['totals']['refunded']);
        $this->assertSame(52, $r['agents'][0]['total']);
        $this->assertSame(1, $r['tenants'][0]['tenant_id']);
    }

    public function testEventsAreFiredOnChargeAndRefund(): void
    {
        $this->agent('jefa', ['is_orchestrator' => true], task: 40);
        $ana = $this->agent('ana', [], hire: 10);
        $this->ledger->fund(1, 200);
        $seen = [];

        \Event::listen('aero.workspaces.charged', function (...$a) use (&$seen) { $seen[] = ['charged', $a[1], $a[2]]; });
        \Event::listen('aero.workspaces.refunded', function (...$a) use (&$seen) { $seen[] = ['refunded', $a[1], $a[2]]; });

        Hiring::hire(1, 'ana');
        $task = Tasks::submit(1, 'Algo');
        Tasks::cancel(1, $task->id);

        $this->assertContains(['charged', 'hire', 10], $seen);
        $this->assertContains(['charged', 'task', (int) $task->charged_points], $seen);
        $this->assertContains(['refunded', 'task', (int) $task->charged_points], $seen);
    }
}

/** Libro de puntos de mentira con la misma interfaz que CreditsLedger. */
class FakeLedger
{
    public array $balances = [];
    public array $txs = [];

    public function fund(int $tenantId, int $amount): void
    {
        $this->balances[$tenantId] = ($this->balances[$tenantId] ?? 0) + $amount;
    }

    public function balance(int $tenantId): int
    {
        return $this->balances[$tenantId] ?? 0;
    }

    public function charge(int $tenantId, int $amount, string $actionCode, array $context): int
    {
        foreach ($this->txs as $id => $tx) {
            if ($tx['key'] === ($context['idempotency_key'] ?? null)) {
                return $id;
            }
        }

        if ($this->balance($tenantId) < $amount) {
            throw new \Aero\Credits\Classes\Exceptions\InsufficientCreditsException('azul', $amount, $this->balance($tenantId));
        }

        $this->balances[$tenantId] -= $amount;
        $id = count($this->txs) + 1;
        $this->txs[$id] = ['tenant' => $tenantId, 'amount' => $amount, 'key' => $context['idempotency_key'] ?? null, 'refunded' => false];

        return $id;
    }

    public function refund(int $transactionId, string $reason): void
    {
        $tx = &$this->txs[$transactionId];

        if (!$tx['refunded']) {
            $tx['refunded'] = true;
            $this->balances[$tx['tenant']] += $tx['amount'];
        }
    }
}
