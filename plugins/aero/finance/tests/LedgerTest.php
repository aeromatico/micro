<?php namespace Aero\Finance\Tests;

use Aero\Finance\Classes\AccountSeeder;
use Aero\Finance\Classes\FinanceException;
use Aero\Finance\Classes\LedgerService;
use Aero\Finance\Classes\MovementService;
use Aero\Finance\Classes\Reports;
use Aero\Finance\Models\Account;
use Aero\Finance\Models\JournalEntry;
use PluginTestCase;

class LedgerTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        AccountSeeder::ensure(1);
        AccountSeeder::ensure(2);
    }

    protected function acc(int $t, string $key): int
    {
        return AccountSeeder::system($t, $key)->id;
    }

    protected function income(int $t, float $amount, array $extra = [], ?array $source = null)
    {
        return app(MovementService::class)->record($t, $extra + [
            'kind' => 'income', 'date' => '2026-10-05', 'amount' => $amount,
            'category_account_id' => $this->acc($t, 'sales'), 'cash_account_id' => $this->acc($t, 'cash'),
            'description' => 'Venta de prueba',
        ], $source);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['accounts', 'movements', 'journal_entries', 'journal_lines', 'settings'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_finance_' . $t), "Falta aero_finance_{$t}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Finance\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());
        foreach ($plugin->registerNavigation() as $item) {
            foreach ($item['permissions'] ?? [] as $p) {
                $this->assertContains($p, $declared);
            }
        }
    }

    public function testUnbalancedEntryIsRejected(): void
    {
        $this->expectException(FinanceException::class);
        app(LedgerService::class)->post(1, '2026-10-05', 'Mal', [
            ['account_id' => $this->acc(1, 'cash'), 'debit' => 100],
            ['account_id' => $this->acc(1, 'sales'), 'credit' => 90],
        ]);
    }

    public function testEntryWithForeignAccountIsRejected(): void
    {
        $this->expectException(FinanceException::class);
        app(LedgerService::class)->post(1, '2026-10-05', 'Ajena', [
            ['account_id' => $this->acc(1, 'cash'), 'debit' => 10],
            ['account_id' => $this->acc(2, 'sales'), 'credit' => 10],
        ]);
    }

    public function testIncomeWithVatSplitsAndBalances(): void
    {
        $m = $this->income(1, 113.00, ['tax_amount' => 13.00]);
        $lines = $m->entry->lines;

        $this->assertEquals(113.00, $lines->sum('debit'));
        $this->assertEquals(113.00, $lines->sum('credit'));
        $this->assertEquals(13.00, (float) $lines->firstWhere('account_id', $this->acc(1, 'vat_debit'))->credit);
        $this->assertEquals(100.00, (float) $lines->firstWhere('account_id', $this->acc(1, 'sales'))->credit);
    }

    public function testExpenseWithVatUsesVatCredit(): void
    {
        $m = app(MovementService::class)->record(1, [
            'kind' => 'expense', 'date' => '2026-10-06', 'amount' => 226.00, 'tax_amount' => 26.00,
            'category_account_id' => $this->acc(1, 'purchases'), 'cash_account_id' => $this->acc(1, 'bank'),
            'description' => 'Compra',
        ]);
        $lines = $m->entry->lines;

        $this->assertEquals(26.00, (float) $lines->firstWhere('account_id', $this->acc(1, 'vat_credit'))->debit);
        $this->assertEquals(226.00, (float) $lines->firstWhere('account_id', $this->acc(1, 'bank'))->credit);
    }

    public function testUsdIsConvertedToBobInTheLedger(): void
    {
        $m = $this->income(1, 10.00, ['currency' => 'USD', 'exchange_rate' => 7]);
        $this->assertEquals(70.00, $m->entry->lines->sum('debit'));
    }

    public function testVoidCreatesReversalAndNetsToZero(): void
    {
        $svc = app(MovementService::class);
        $m = $this->income(1, 50.00);
        $svc->void($m, 'error');

        $this->assertSame('void', $m->fresh()->status);
        $this->assertSame('void', $m->entry->fresh()->status);
        $this->assertEquals(1, JournalEntry::where('reversal_of_id', $m->entry_id)->count());

        $cash = (new Reports(1))->ledger($this->acc(1, 'cash'), now()->startOfMonth(), now()->endOfMonth()->addYear());
        $this->assertEquals(0.0, $cash['closing']);
    }

    public function testSourceIsIdempotent(): void
    {
        $ledger = app(LedgerService::class);
        $lines = [['account_id' => $this->acc(1, 'cash'), 'debit' => 5], ['account_id' => $this->acc(1, 'sales'), 'credit' => 5]];
        $src = ['type' => 'shop_order', 'id' => 9, 'event' => 'paid'];

        $a = $ledger->post(1, '2026-10-05', 'x', $lines, $src);
        $b = $ledger->post(1, '2026-10-05', 'x', $lines, $src);

        $this->assertSame($a->id, $b->id);
        $this->assertEquals(1, JournalEntry::where('tenant_id', 1)->count());
    }

    public function testPostedEntryCannotBeEditedOrDeleted(): void
    {
        $entry = $this->income(1, 20.00)->entry;
        $entry->description = 'cambiado';
        try {
            $entry->save();
            $this->fail('Debió rechazar la edición');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('no se edita', $e->getMessage());
        }
        $this->expectException(\ApplicationException::class);
        $entry->delete();
    }

    public function testTrialBalanceIsBalancedAndTenantsAreIsolated(): void
    {
        $this->income(1, 100.00);
        $this->income(2, 999.00);

        $t1 = (new Reports(1))->trialBalance(now()->addYear());
        $this->assertEquals($t1['totals']['debit'], $t1['totals']['credit']);
        $this->assertEquals(100.00, $t1['totals']['debit']);

        $this->assertEquals(0, Account::where('tenant_id', 1)->whereIn('id', Account::where('tenant_id', 2)->pluck('id'))->count());
        $this->assertEquals(100.00, JournalEntry::forTenant(1)->first()->lines->sum('debit'));
        $this->assertEquals(0, JournalEntry::forTenant(null)->count()); // sin tenant: falla cerrado
    }

    public function testMonthlyAndCategoryReports(): void
    {
        $this->income(1, 200.00);
        app(MovementService::class)->record(1, [
            'kind' => 'expense', 'date' => '2026-10-07', 'amount' => 50.00,
            'category_account_id' => AccountSeeder::system(1, 'other_expense')->id, 'cash_account_id' => $this->acc(1, 'cash'),
            'description' => 'Gasto',
        ]);
        $from = \Carbon\Carbon::parse('2026-10-01');
        $to = \Carbon\Carbon::parse('2026-10-31');
        $r = new Reports(1);

        $m = $r->monthly($from, $to);
        $this->assertEquals(['income' => 200.0, 'expense' => 50.0, 'result' => 150.0], $m['2026-10']);
        $this->assertCount(2, $r->byCategory($from, $to));
    }
}
