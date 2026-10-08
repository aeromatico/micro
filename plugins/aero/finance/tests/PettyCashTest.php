<?php namespace Aero\Finance\Tests;

use Aero\Finance\Classes\AccountSeeder;
use Aero\Finance\Classes\FinanceException;
use Aero\Finance\Classes\MovementService;
use Aero\Finance\Classes\PettyCashService;
use Aero\Finance\Classes\Reports;
use Aero\Finance\Models\JournalEntry;
use Aero\Finance\Models\PettyFund;
use PluginTestCase;

class PettyCashTest extends PluginTestCase
{
    protected PettyFund $fund;

    public function setUp(): void
    {
        parent::setUp();
        AccountSeeder::ensure(1);
        $this->fund = PettyFund::create(['tenant_id' => 1, 'name' => 'Oficina']);
    }

    protected function svc(): PettyCashService
    {
        return app(PettyCashService::class);
    }

    protected function cash(): int
    {
        return AccountSeeder::system(1, 'cash')->id;
    }

    public function testFundCreatesItsOwnAssetAccount(): void
    {
        $this->assertSame('1.1.04.01', $this->fund->account->code);
        $this->assertSame('asset', $this->fund->account->type);
        $second = PettyFund::create(['tenant_id' => 1, 'name' => 'Taller']);
        $this->assertSame('1.1.04.02', $second->account->code);
    }

    public function testFundingMovesCashIntoThePettyAccountInTheLedger(): void
    {
        $this->svc()->fund($this->fund, 500, $this->cash());

        $this->assertEquals(500.0, $this->fund->fresh()->balance);
        $trial = (new Reports(1))->trialBalance(now()->addYear());
        $this->assertEquals($trial['totals']['debit'], $trial['totals']['credit']);
    }

    public function testExpenseFromPettyCashIsAMovementAndLowersBalance(): void
    {
        $this->svc()->fund($this->fund, 500, $this->cash());
        $m = $this->svc()->expense($this->fund, [
            'amount' => 113, 'tax_amount' => 13, 'description' => 'Útiles',
            'category_account_id' => AccountSeeder::system(1, 'other_expense')->id,
        ]);

        $this->assertNotNull($m->entry_id);
        $this->assertEquals(387.0, $this->fund->fresh()->balance);
    }

    public function testCannotSpendMoreThanTheFundHas(): void
    {
        $this->svc()->fund($this->fund, 50, $this->cash());
        $this->expectException(FinanceException::class);
        $this->svc()->expense($this->fund, ['amount' => 80, 'description' => 'x', 'category_account_id' => AccountSeeder::system(1, 'other_expense')->id]);
    }

    public function testGenericMovementCannotOverdrawPettyCashEither(): void
    {
        $this->svc()->fund($this->fund, 50, $this->cash());
        $this->expectException(FinanceException::class);
        app(MovementService::class)->record(1, [
            'kind' => 'expense', 'date' => '2026-10-08', 'amount' => 80, 'description' => 'x',
            'category_account_id' => AccountSeeder::system(1, 'other_expense')->id, 'cash_account_id' => $this->fund->account_id,
        ]);
    }

    public function testCountPostsShortageAndSurplus(): void
    {
        $this->svc()->fund($this->fund, 100, $this->cash());

        $short = $this->svc()->count($this->fund, 90);
        $this->assertEquals(-10.0, (float) $short->difference);
        $this->assertEquals(90.0, $this->fund->fresh()->balance);

        $over = $this->svc()->count($this->fund, 95);
        $this->assertEquals(5.0, (float) $over->difference);
        $this->assertEquals(95.0, $this->fund->fresh()->balance);

        $ok = $this->svc()->count($this->fund, 95);
        $this->assertNull($ok->entry_id); // sin diferencia no hay asiento
    }

    public function testReturnAndVoid(): void
    {
        $op = $this->svc()->fund($this->fund, 200, $this->cash());
        $this->svc()->giveBack($this->fund, 50, $this->cash());
        $this->assertEquals(150.0, $this->fund->fresh()->balance);

        // El fondeo ya no se puede anular: se devolvió/gastó parte.
        $this->expectException(FinanceException::class);
        $this->svc()->void($op->fresh(), 'x');
    }

    public function testVoidFundingReversesTheEntry(): void
    {
        $op = $this->svc()->fund($this->fund, 200, $this->cash());
        $this->svc()->void($op, 'error');

        $this->assertEquals(0.0, $this->fund->fresh()->balance);
        $this->assertSame('void', $op->entry->fresh()->status);
        $this->assertEquals(1, JournalEntry::where('reversal_of_id', $op->entry_id)->count());
    }

    public function testCounterAccountMustBeAnotherAsset(): void
    {
        $this->expectException(FinanceException::class);
        $this->svc()->fund($this->fund, 10, $this->fund->account_id);
    }
}
