<?php namespace Aero\Credits\Tests;

use Aero\Credits\Classes\Workflows\CreditNodes;
use Aero\Credits\Models\CreditPurchase;
use Aero\Credits\Models\Settings;
use Aero\Pay\Classes\Drivers\PaymentDriverInterface;
use Aero\Pay\Classes\Dto\CreateIntentData;
use Aero\Pay\Classes\Dto\PaymentIntentResult;
use Aero\Pay\Classes\Dto\PaymentNotification;
use Aero\Pay\Classes\Dto\PaymentStatusResult;
use Aero\Pay\Classes\PaymentDriverManager;
use Aero\Pay\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PluginTestCase;

/** Banco de mentira: devuelve un QR en memoria. */
class FakeRechargeBank implements PaymentDriverInterface
{
    public static function code(): string { return 'fake'; }
    public static function label(): string { return 'Banco de prueba'; }
    public static function flow(): string { return 'qr_dynamic'; }

    public function generateQr(BankAccount $account, CreateIntentData $data): PaymentIntentResult
    {
        return new PaymentIntentResult('EXT-' . $data->transactionId, base64_encode('png'));
    }

    public function cancelQr(BankAccount $account, string $externalQrId): void {}
    public function getStatus(BankAccount $account, string $externalQrId): PaymentStatusResult { return new PaymentStatusResult('pending'); }
    public function testConnection(BankAccount $account): array { return []; }
    public function listPaid(BankAccount $account, \DateTimeInterface $date): array { return []; }
    public function supportsWebhook(): bool { return false; }
    public function parseWebhookPayment(Request $request): ?PaymentNotification { return null; }
    public function verifyWebhookSignature(Request $request, BankAccount $account): bool { return false; }
    public function webhookAcknowledgement(bool $ok, string $message = ''): array { return []; }
}

class CreditNodesTest extends PluginTestCase
{
    /** Solo las tablas de monedas y cobros; no se arrancan plugins ajenos. */
    protected $autoRegister = false;

    protected function migrateCurrentPlugin()
    {
    }

    public function setUp(): void
    {
        parent::setUp();

        \Schema::create('aero_credits_types', function ($t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('label');
            $t->string('color', 7)->default('#3b82f6');
            $t->decimal('usd_value', 10, 4)->default(0.01);
            $t->decimal('price_bob', 10, 4)->default(0);
            $t->boolean('is_exchangeable')->default(false);
            $t->boolean('is_money')->default(false);
            $t->unsignedInteger('low_balance_threshold')->default(50);
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        \Schema::create('aero_credits_accounts', function ($t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedBigInteger('credit_type_id');
            $t->bigInteger('balance')->default(0);
            $t->timestamps();
        });

        \Schema::create('aero_credits_purchases', function ($t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->decimal('amount_bob', 10, 2);
            $t->bigInteger('wallet_units')->default(0);
            $t->string('status', 16)->default('pending');
            $t->text('lines');
            $t->unsignedBigInteger('qr_code_id')->nullable();
            $t->string('payment_reference', 100)->nullable()->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->unsignedInteger('created_by_user_id')->nullable();
            $t->timestamps();
        });

        \Schema::create('aero_pay_settings', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->boolean('enable_branches')->default(true);
            $t->boolean('enable_usd')->default(false);
            $t->integer('default_due_date_days')->default(7);
            $t->boolean('tax_enabled')->default(false);
            $t->decimal('tax_percentage', 5, 2)->nullable();
            $t->boolean('tax_included')->default(true);
            $t->timestamps();
        });

        \Schema::create('aero_pay_bank_accounts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('bank_code');
            $t->string('label');
            $t->string('environment')->default('sandbox');
            $t->text('credentials')->nullable();
            $t->text('credentials_json')->nullable();
            $t->string('status')->default('active');
            $t->timestamp('static_qr_expires_at')->nullable();
            $t->text('manual_fallback_instructions')->nullable();
            $t->string('outbound_webhook_url')->nullable();
            $t->string('outbound_webhook_secret')->nullable();
            $t->text('cached_token')->nullable();
            $t->timestamp('token_expires_at')->nullable();
            $t->timestamp('outbound_webhook_last_sent_at')->nullable();
            $t->timestamps();
        });

        \Schema::create('aero_pay_qr_codes', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->unsignedBigInteger('bank_account_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('bank_code');
            $t->string('flow')->nullable();
            $t->string('origin', 20)->nullable();
            $t->uuid('internal_reference')->unique();
            $t->string('external_qr_id')->nullable();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3)->default('BOB');
            $t->string('description')->nullable();
            $t->date('due_date')->nullable();
            $t->boolean('single_use')->default(true);
            $t->boolean('modify_amount')->default(false);
            $t->string('status')->default('pending');
            $t->longText('qr_image')->nullable();
            $t->text('redirect_url')->nullable();
            $t->text('return_url')->nullable();
            $t->text('cancel_url')->nullable();
            $t->json('raw_response')->nullable();
            $t->string('branch_code')->nullable();
            $t->string('customer_nit')->nullable();
            $t->decimal('tax_percentage', 5, 2)->nullable();
            $t->decimal('tax_amount', 12, 2)->nullable();
            $t->string('alert_prefix')->nullable();
            $t->string('alert_phone')->nullable();
            $t->string('alert_email')->nullable();
            $t->json('ad_tracking')->nullable();
            $t->timestamps();
        });

        $manager = new PaymentDriverManager();
        $manager->register('fake', FakeRechargeBank::class);
        $this->app->singleton(PaymentDriverManager::class, fn () => $manager);

        // Monedas: bronce (Bs 0,50 c/u), oro (Bs 5 c/u) y la billetera de Bs.
        $this->type('bronce', 'Bronce', 0.5);
        $this->type('oro', 'Oro', 5);
        $this->type('bs', 'Bs', 0, true);
    }

    protected function type(string $code, string $label, float $price, bool $money = false): int
    {
        return DB::table('aero_credits_types')->insertGetId([
            'code' => $code, 'label' => $label, 'price_bob' => $price, 'is_money' => $money,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function setBalance(int $tenant, string $code, int $balance): void
    {
        DB::table('aero_credits_accounts')->insert([
            'tenant_id' => $tenant, 'credit_type_id' => DB::table('aero_credits_types')->where('code', $code)->value('id'),
            'balance' => $balance, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function platformBank(): void
    {
        $id = DB::table('aero_pay_bank_accounts')->insertGetId([
            'tenant_id' => 1, 'bank_code' => 'fake', 'label' => 'Plataforma', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Settings::set('purchase_bank_account_id', $id);
    }

    public function testDefinitionsAreCallableAndFlagTheMoneyNode(): void
    {
        $defs = CreditNodes::definitions();

        $this->assertSame(['credits.balance', 'credits.recharge', 'credits.purchase_status'], array_keys($defs));

        foreach ($defs as $type => $def) {
            $this->assertTrue(is_callable($def['handler']), $type);
        }

        $this->assertContains('credits.recharge', \Aero\Workflows\Classes\GraphValidator::SIDE_EFFECT_TYPES);
        $this->assertNotContains('credits.balance', \Aero\Workflows\Classes\GraphValidator::SIDE_EFFECT_TYPES);
    }

    public function testBalanceShowsCoinsAndWalletOfTheWorkflowTenantOnly(): void
    {
        $this->setBalance(7, 'bronce', 120);
        $this->setBalance(7, 'bs', 125000); // Bs 12,50
        $this->setBalance(8, 'bronce', 999);

        $r = CreditNodes::balance([], [], 7);

        $this->assertSame('saldo', $r['var']);
        $this->assertEquals([['code' => 'bronce', 'label' => 'Bronce', 'balance' => 120], ['code' => 'oro', 'label' => 'Oro', 'balance' => 0]], $r['output']['balances']);
        $this->assertSame(12.5, $r['output']['wallet_bob']);
        $this->assertStringContainsString('Bronce: 120', $r['output']['text']);
        $this->assertStringContainsString('Billetera: Bs 12,50', $r['output']['text']);
    }

    public function testRechargeIssuesAPlatformQrForTheTenant(): void
    {
        $this->platformBank();

        $r = CreditNodes::recharge(['amount' => '100', 'coin' => 'bronce'], [], 7);

        $this->assertSame('created', $r['handle']);
        $this->assertSame('recarga', $r['var']);
        $this->assertSame(100.0, $r['output']['amount_bob']);
        $this->assertSame(200, $r['output']['coins']); // 100 / 0,50
        $this->assertSame('pending', $r['output']['status']);
        $this->assertStringContainsString('/image', $r['output']['image_url']);

        $purchase = CreditPurchase::first();
        $this->assertSame(7, (int) $purchase->tenant_id);
        $this->assertSame($r['output']['reference'], $purchase->payment_reference);
        $this->assertSame(0, (int) DB::table('aero_credits_accounts')->count(), 'ningún nodo acredita monedas: solo el pago confirmado');
    }

    public function testRechargeRejectsAmountsOutsideTheOfferedOnesAndUnknownCoins(): void
    {
        $this->platformBank();

        $this->assertSame('invalid_amount', CreditNodes::recharge(['amount' => '37'], [], 7)['output']['error']);
        $this->assertSame('invalid_amount', CreditNodes::recharge(['amount' => ''], [], 7)['output']['error']);
        $this->assertSame('invalid_request', CreditNodes::recharge(['amount' => '100', 'coin' => 'inexistente'], [], 7)['output']['error']);
        $this->assertSame(0, CreditPurchase::count());
    }

    public function testRechargeReportsWhenThePlatformHasNoCollectionAccount(): void
    {
        $r = CreditNodes::recharge(['amount' => '100'], [], 7);

        $this->assertSame('failed', $r['handle']);
        $this->assertSame('unavailable', $r['output']['error']);
        $this->assertSame(0, CreditPurchase::count());
    }

    public function testPurchaseStatusRoutesByStateAndNeverShowsAnotherTenantsPurchase(): void
    {
        $this->platformBank();
        $ref = CreditNodes::recharge(['amount' => '50', 'coin' => 'oro'], [], 7)['output']['reference'];

        $this->assertSame('pending', CreditNodes::purchaseStatus(['reference' => $ref], [], 7)['handle']);
        $this->assertSame('not_found', CreditNodes::purchaseStatus(['reference' => $ref], [], 8)['handle']);
        $this->assertSame('not_found', CreditNodes::purchaseStatus(['reference' => ''], [], 7)['handle']);

        // Plazo vencido: aunque el barrido no la haya marcado, ya no está pendiente.
        CreditPurchase::query()->update(['expires_at' => now()->subMinute()]);
        $this->assertSame('expired', CreditNodes::purchaseStatus(['reference' => $ref], [], 7)['handle']);

        CreditPurchase::query()->update(['status' => 'paid', 'paid_at' => now()]);
        $paid = CreditNodes::purchaseStatus(['reference' => $ref], [], 7);
        $this->assertSame('paid', $paid['handle']);
        $this->assertNotNull($paid['output']['paid_at']);

        CreditPurchase::query()->update(['status' => 'review']);
        $this->assertSame('review', CreditNodes::purchaseStatus(['reference' => $ref], [], 7)['handle']);
    }

    public function testNodesRefuseToRunWithoutATenant(): void
    {
        $this->expectException(\RuntimeException::class);
        CreditNodes::balance([], [], null);
    }
}
