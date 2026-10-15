<?php namespace Aero\Crm\Tests;

use Aero\Crm\Classes\AgentTools;
use Aero\Crm\Models\AgentChange;
use Illuminate\Support\Facades\DB;
use PluginTestCase;

/**
 * Herramientas de CRM del agente: tenant y persona los pone el motor, guardar exige
 * plan, editar exige confirmación, todo se deshace, y los informes salen del código.
 * Tablas mínimas (no se arrancan los plugins).
 */
class AgentToolsTest extends PluginTestCase
{
    protected const PLAN = 'Registrar al cliente nuevo y abrirle un negocio.';

    protected $autoRegister = false;
    protected array $ctx;

    protected function migrateCurrentPlugin()
    {
    }

    public function setUp(): void
    {
        parent::setUp();

        \Schema::create('aero_sites_tenants', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        DB::table('aero_sites_tenants')->insert([['id' => 1, 'name' => 'Uno'], ['id' => 2, 'name' => 'Dos']]);

        $ts = fn ($t) => [$t->timestamp('created_at')->nullable(), $t->timestamp('updated_at')->nullable()];

        \Schema::create('aero_crm_companies', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('name'); $t->string('website')->nullable(); $t->string('industry')->nullable();
            $t->string('phone')->nullable(); $t->string('address')->nullable(); $t->longText('social_links')->nullable(); $t->unsignedInteger('owner_id')->nullable(); $ts($t);
        });
        \Schema::create('aero_crm_contacts', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('company_id')->nullable(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable();
            $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->longText('social_links')->nullable(); $t->string('source')->nullable();
            $t->unsignedInteger('owner_id')->nullable(); $t->unsignedBigInteger('shop_customer_id')->nullable(); $t->unsignedBigInteger('hello_contact_id')->nullable(); $ts($t);
        });
        \Schema::create('aero_crm_leads', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('name'); $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->string('company_name')->nullable();
            $t->string('source')->nullable(); $t->string('status')->default('new'); $t->unsignedInteger('owner_id')->nullable();
            $t->unsignedBigInteger('converted_contact_id')->nullable(); $t->unsignedBigInteger('converted_deal_id')->nullable(); $t->boolean('in_pipeline')->default(true); $ts($t);
        });
        \Schema::create('aero_crm_pipelines', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('name'); $ts($t);
        });
        \Schema::create('aero_crm_pipeline_stages', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('pipeline_id'); $t->string('name'); $t->unsignedInteger('sort_order')->default(0);
            $t->string('color')->nullable(); $t->boolean('is_won')->default(false); $t->boolean('is_lost')->default(false); $ts($t);
        });
        \Schema::create('aero_crm_deals', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('pipeline_id'); $t->unsignedBigInteger('stage_id'); $t->unsignedBigInteger('contact_id')->nullable();
            $t->unsignedBigInteger('company_id')->nullable(); $t->string('title'); $t->decimal('value', 14, 2)->default(0); $t->string('currency', 5)->nullable();
            $t->unsignedInteger('owner_id')->nullable(); $t->unsignedBigInteger('team_id')->nullable(); $t->date('expected_close_date')->nullable(); $t->string('status')->default('open');
            $t->unsignedInteger('sort_order')->default(0); $t->timestamp('closed_at')->nullable(); $t->boolean('in_pipeline')->default(true); $ts($t);
        });
        \Schema::create('aero_crm_activities', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('related_type')->nullable(); $t->unsignedBigInteger('related_id')->nullable(); $t->string('type'); $t->string('subject');
            $t->string('status', 20)->default('pending'); $t->text('description')->nullable(); $t->timestamp('due_at')->nullable(); $t->timestamp('completed_at')->nullable();
            $t->unsignedInteger('owner_id')->nullable(); $ts($t);
        });
        \Schema::create('aero_crm_collection_items', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('contact_id')->nullable(); $t->unsignedBigInteger('contact_list_id')->nullable();
            $t->unsignedInteger('owner_id')->nullable(); $t->string('concept'); $t->decimal('amount', 12, 2); $t->string('currency', 8)->nullable(); $t->date('due_date');
            $t->string('status', 20)->default('pending'); $t->timestamp('paid_at')->nullable(); $t->timestamp('last_reminder_at')->nullable(); $t->unsignedInteger('reminder_count')->default(0);
            $t->text('notes')->nullable(); $t->string('payment_reference')->nullable(); $ts($t);
        });
        \Schema::create('aero_crm_tickets', function ($t) use ($ts) {
            $t->id(); $t->unsignedBigInteger('tenant_id')->nullable(); $t->string('subject'); $t->string('status', 20)->default('open'); $t->string('priority', 20)->default('normal');
            $t->string('source', 20)->nullable(); $t->unsignedInteger('seq')->nullable(); $t->unsignedBigInteger('contact_id')->nullable(); $t->string('requester_name')->nullable();
            $t->string('requester_email')->nullable(); $t->timestamp('first_response_at')->nullable(); $ts($t);
        });

        \Aero\Crm\Tests\AgentToolsTest::migrateChanges();

        $user = \Backend\Models\User::create([
            'login' => 'ana', 'email' => 'ana@test.dev', 'first_name' => 'Ana', 'last_name' => 'T',
            'password' => 'Secreto-123', 'password_confirmation' => 'Secreto-123',
        ]);
        $user->forceFill(['is_superuser' => true, 'is_activated' => true])->save();
        $this->ctx = ['user_id' => $user->id];
    }

    public static function migrateChanges(): void
    {
        (require __DIR__ . '/../updates/create_agent_changes_table.php')->up();
    }

    protected function contact(int $tenant, array $extra = []): int
    {
        return DB::table('aero_crm_contacts')->insertGetId($extra + ['tenant_id' => $tenant, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@x.com', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function seedPipeline(int $tenant): array
    {
        $p = DB::table('aero_crm_pipelines')->insertGetId(['tenant_id' => $tenant, 'name' => 'Ventas', 'created_at' => now(), 'updated_at' => now()]);
        $stages = [];

        foreach ([['Nuevo', 0, 0], ['Propuesta', 1, 0], ['Ganado', 2, 1], ['Perdido', 3, 0]] as $i => [$name, $sort, $won]) {
            $stages[$name] = DB::table('aero_crm_pipeline_stages')->insertGetId([
                'tenant_id' => $tenant, 'pipeline_id' => $p, 'name' => $name, 'sort_order' => $sort, 'is_won' => $won, 'is_lost' => $name === 'Perdido' ? 1 : 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$p, $stages];
    }

    protected function deal(int $tenant, int $p, int $stage, array $extra = []): int
    {
        return DB::table('aero_crm_deals')->insertGetId($extra + ['tenant_id' => $tenant, 'pipeline_id' => $p, 'stage_id' => $stage, 'title' => 'Negocio', 'value' => 100, 'currency' => 'BOB',
            'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function testNothingRunsWithoutAPersonOrPermission(): void
    {
        $this->assertArrayHasKey('error', AgentTools::overview([], 1, []));
        $this->assertArrayHasKey('error', AgentTools::overview([], 99, $this->ctx));

        $plain = \Backend\Models\User::create(['login' => 'sin', 'email' => 'sin@test.dev', 'first_name' => 'S', 'last_name' => 'P', 'password' => 'Secreto-123', 'password_confirmation' => 'Secreto-123']);
        $out = AgentTools::search(['type' => 'contact'], 1, ['user_id' => $plain->id]);
        $this->assertArrayHasKey('error', $out);
    }

    public function testSearchAndGetAreScopedToTheTenant(): void
    {
        $mine = $this->contact(1);
        $theirs = $this->contact(2, ['first_name' => 'Ajena', 'email' => 'a@y.com']);

        $found = AgentTools::search(['type' => 'contact', 'q' => 'a'], 1, $this->ctx);
        $this->assertSame([$mine], array_column($found['items'], 'id'));

        $this->assertArrayHasKey('error', AgentTools::get(['type' => 'contact', 'id' => $theirs], 1, $this->ctx));
        $this->assertSame($mine, AgentTools::get(['type' => 'contact', 'id' => $mine], 1, $this->ctx)['record']['id']);
        $this->assertStringContainsString('DATOS', AgentTools::get(['type' => 'contact', 'id' => $mine], 1, $this->ctx)['note']);
    }

    public function testSavingNeedsPlanAndEditsNeedConfirmation(): void
    {
        $noPlan = AgentTools::companySave(['name' => 'ACME'], 1, $this->ctx);
        $this->assertFalse($noPlan['saved']);

        $made = AgentTools::companySave(['name' => 'ACME', 'industry' => 'Textil', 'agreed_plan' => static::PLAN], 1, $this->ctx);
        $this->assertTrue($made['saved']);
        $this->assertSame(1, (int) DB::table('aero_crm_companies')->value('tenant_id'));
        $this->assertSame($this->ctx['user_id'], (int) DB::table('aero_crm_companies')->value('owner_id'));

        // Duplicado por nombre: no se crea otro.
        $dup = AgentTools::companySave(['name' => 'ACME', 'agreed_plan' => static::PLAN], 1, $this->ctx);
        $this->assertFalse($dup['saved']);
        $this->assertSame(1, DB::table('aero_crm_companies')->count());

        // Editar: primero muestra el antes→después y no cambia nada.
        $preview = AgentTools::companySave(['id' => $made['id'], 'industry' => 'Minería', 'agreed_plan' => static::PLAN], 1, $this->ctx);
        $this->assertFalse($preview['confirmed']);
        $this->assertSame('Textil', $preview['changes']['industry']['before']);
        $this->assertSame('Textil', DB::table('aero_crm_companies')->value('industry'));

        $done = AgentTools::companySave(['id' => $made['id'], 'industry' => 'Minería', 'agreed_plan' => static::PLAN, 'confirm' => true], 1, $this->ctx);
        $this->assertTrue($done['saved']);
        $this->assertSame('Minería', DB::table('aero_crm_companies')->value('industry'));

        // Otro tenant no puede editarla.
        $steal = AgentTools::companySave(['id' => $made['id'], 'industry' => 'X', 'agreed_plan' => static::PLAN, 'confirm' => true], 2, $this->ctx);
        $this->assertFalse($steal['saved']);
    }

    public function testUndoRestoresEditsAndRemovesUntouchedCreations(): void
    {
        $id = AgentTools::companySave(['name' => 'ACME', 'industry' => 'Textil', 'agreed_plan' => static::PLAN], 1, $this->ctx)['id'];
        AgentTools::companySave(['id' => $id, 'industry' => 'Minería', 'agreed_plan' => static::PLAN, 'confirm' => true], 1, $this->ctx);

        $this->assertFalse(AgentTools::undo([], 1, $this->ctx)['confirmed']);
        $this->assertSame('Minería', DB::table('aero_crm_companies')->value('industry'));

        $this->assertTrue(AgentTools::undo(['confirm' => true], 1, $this->ctx)['confirmed']);
        $this->assertSame('Textil', DB::table('aero_crm_companies')->value('industry'));

        // Deshacer la creación elimina la empresa (nada depende de ella).
        $this->assertTrue(AgentTools::undo(['confirm' => true], 1, $this->ctx)['confirmed']);
        $this->assertSame(0, DB::table('aero_crm_companies')->count());
        $this->assertNotNull(AgentChange::first()->undone_at);

        // Si algo se colgó de lo creado, no se elimina.
        $id2 = AgentTools::companySave(['name' => 'Beta', 'agreed_plan' => static::PLAN], 1, $this->ctx)['id'];
        $this->contact(1, ['company_id' => $id2]);
        $this->assertArrayHasKey('error', AgentTools::undo(['confirm' => true], 1, $this->ctx));
        $this->assertSame(1, DB::table('aero_crm_companies')->count());
    }

    public function testDealStageMovesCloseTheDealLikeThePanelDoes(): void
    {
        [$p, $stages] = $this->seedPipeline(1);
        $id = AgentTools::dealSave(['title' => 'Cliente grande', 'value' => 500, 'currency' => 'bob', 'agreed_plan' => static::PLAN], 1, $this->ctx)['id'];

        $deal = DB::table('aero_crm_deals')->find($id);
        $this->assertSame((int) $stages['Nuevo'], (int) $deal->stage_id, 'nace en la primera etapa');
        $this->assertSame('open', $deal->status);
        $this->assertSame('BOB', $deal->currency);

        $this->assertFalse(AgentTools::dealSave(['id' => $id, 'stage' => 'Ganado', 'agreed_plan' => static::PLAN], 1, $this->ctx)['confirmed']);
        $this->assertSame('open', DB::table('aero_crm_deals')->find($id)->status);

        AgentTools::dealSave(['id' => $id, 'stage' => 'ganado', 'agreed_plan' => static::PLAN, 'confirm' => true], 1, $this->ctx);
        $won = DB::table('aero_crm_deals')->find($id);
        $this->assertSame('won', $won->status);
        $this->assertNotNull($won->closed_at);

        $bad = AgentTools::dealSave(['id' => $id, 'stage' => 'Inventada', 'agreed_plan' => static::PLAN, 'confirm' => true], 1, $this->ctx);
        $this->assertFalse($bad['saved']);

        // Un contacto de otro tenant no se puede ligar.
        $foreign = $this->contact(2);
        $this->assertFalse(AgentTools::dealSave(['title' => 'X', 'contact_id' => $foreign, 'agreed_plan' => static::PLAN], 1, $this->ctx)['saved']);
    }

    public function testLeadConversionNeedsConfirmationAndIsIdempotent(): void
    {
        $lead = AgentTools::leadSave(['name' => 'Luis Mamani', 'email' => 'luis@x.com', 'company_name' => 'Mamani SRL', 'source' => 'web', 'agreed_plan' => static::PLAN], 1, $this->ctx);
        $this->assertTrue($lead['saved']);
        $this->assertSame('new', DB::table('aero_crm_leads')->value('status'));

        $this->assertFalse(AgentTools::leadSave(['name' => 'X', 'status' => 'raro', 'agreed_plan' => static::PLAN], 1, $this->ctx)['saved']);

        $this->assertFalse(AgentTools::leadConvert(['id' => $lead['id'], 'agreed_plan' => static::PLAN], 1, $this->ctx)['confirmed']);
        $this->assertSame(0, DB::table('aero_crm_deals')->count());
    }

    public function testCollectionStatusOnlyChangesPendingOnesOfTheTenant(): void
    {
        $mine = DB::table('aero_crm_collection_items')->insertGetId(['tenant_id' => 1, 'concept' => 'Cuota', 'amount' => 50, 'currency' => 'BOB', 'due_date' => now()->subDays(40)->toDateString(), 'status' => 'pending']);
        $theirs = DB::table('aero_crm_collection_items')->insertGetId(['tenant_id' => 2, 'concept' => 'Ajena', 'amount' => 50, 'currency' => 'BOB', 'due_date' => now()->toDateString(), 'status' => 'pending']);

        $this->assertArrayHasKey('error', AgentTools::collectionStatus(['id' => $theirs, 'status' => 'paid', 'agreed_plan' => static::PLAN, 'confirm' => true], 1, $this->ctx));
        $this->assertFalse(AgentTools::collectionStatus(['id' => $mine, 'status' => 'paid', 'agreed_plan' => static::PLAN], 1, $this->ctx)['confirmed']);
        $this->assertSame('pending', DB::table('aero_crm_collection_items')->find($mine)->status);

        $this->assertSame('paid', AgentTools::collectionStatus(['id' => $mine, 'status' => 'paid', 'agreed_plan' => static::PLAN, 'confirm' => true], 1, $this->ctx)['status']);
        $this->assertNotNull(DB::table('aero_crm_collection_items')->find($mine)->paid_at);
        $this->assertArrayHasKey('error', AgentTools::collectionStatus(['id' => $mine, 'status' => 'void', 'agreed_plan' => static::PLAN, 'confirm' => true], 1, $this->ctx), 'ya no está pendiente');
    }

    public function testReportsAreComputedByCodeAndTenantScoped(): void
    {
        [$p, $stages] = $this->seedPipeline(1);
        [$p2, $stages2] = $this->seedPipeline(2);

        $this->deal(1, $p, $stages['Nuevo'], ['value' => 100]);
        $this->deal(1, $p, $stages['Propuesta'], ['value' => 300]);
        $this->deal(1, $p, $stages['Ganado'], ['value' => 1000, 'status' => 'won', 'closed_at' => now()->subDays(2), 'created_at' => now()->subDays(12)]);
        $this->deal(1, $p, $stages['Perdido'], ['value' => 50, 'status' => 'lost', 'closed_at' => now()->subDays(1)]);
        $this->deal(2, $p2, $stages2['Ganado'], ['value' => 9999, 'status' => 'won', 'closed_at' => now()]);

        $r = AgentTools::report(['report' => 'sales_funnel', 'days' => 30], 1, $this->ctx);

        $this->assertSame(1, $r['won_in_period']['deals']);
        $this->assertSame(['BOB' => 1000.0], $r['won_in_period']['value_by_currency']);
        $this->assertSame(1, $r['lost_in_period']['deals']);
        $this->assertSame(50.0, $r['win_rate_pct']);
        $this->assertSame(['BOB' => 400.0], $r['open']['value_by_currency']);
        $this->assertSame(10.0, $r['avg_cycle_days']);
        $this->assertSame(['Nuevo', 'Propuesta', 'Ganado', 'Perdido'], array_column($r['by_stage'], 'stage'));

        $this->assertArrayHasKey('error', AgentTools::report(['report' => 'inventado'], 1, $this->ctx));
    }

    public function testCollectionsAgingBucketsAndContactQuality(): void
    {
        foreach ([[-5, 100], [10, 200], [45, 300], [120, 400]] as [$late, $amount]) {
            DB::table('aero_crm_collection_items')->insert(['tenant_id' => 1, 'concept' => "C{$late}", 'amount' => $amount, 'currency' => 'BOB', 'due_date' => now()->subDays($late)->toDateString(), 'status' => 'pending']);
        }

        $a = AgentTools::report(['report' => 'collections_aging'], 1, $this->ctx)['aging'];
        $this->assertSame(100.0, $a['not_due']['BOB']['amount']);
        $this->assertSame(200.0, $a['1_30']['BOB']['amount']);
        $this->assertSame(300.0, $a['31_60']['BOB']['amount']);
        $this->assertSame(400.0, $a['over_90']['BOB']['amount']);

        $this->contact(1, ['email' => 'dup@x.com']);
        $this->contact(1, ['email' => 'dup@x.com', 'phone' => '', 'first_name' => null, 'last_name' => null]);
        $q = AgentTools::report(['report' => 'contacts'], 1, $this->ctx)['data_quality'];
        $this->assertSame(1, $q['duplicate_emails']);
        $this->assertSame(1, $q['without_name']);

        $this->assertSame(1, AgentTools::overview([], 1, $this->ctx)['duplicates']['by_email']);
    }

    public function testContactDuplicatesAreNotCreated(): void
    {
        $existing = $this->contact(1, ['email' => 'dup@x.com']);

        $out = AgentTools::contactSave(['first_name' => 'Otra', 'email' => 'dup@x.com', 'agreed_plan' => static::PLAN], 1, $this->ctx);

        $this->assertFalse($out['saved']);
        $this->assertSame($existing, $out['duplicate']['id']);
        $this->assertSame(1, DB::table('aero_crm_contacts')->count());

        $this->assertFalse(AgentTools::contactSave(['first_name' => 'X', 'email' => 'no-es-correo', 'agreed_plan' => static::PLAN], 1, $this->ctx)['saved']);
    }

    public function testToolsAreAnnouncedPrivatelyWithTheirCategory(): void
    {
        $tools = AgentTools::tools();

        $this->assertArrayHasKey('crm_report', $tools);

        foreach ($tools as $t) {
            $this->assertSame(AgentTools::CATEGORY, $t['category']);
            $this->assertIsCallable($t['handler']);
        }

        // Nada borra ni crea cobros automáticos desde aquí.
        $this->assertSame([], array_values(array_filter(array_keys($tools), fn ($n) => str_contains($n, 'delete') || str_contains($n, 'reminder'))));
    }
}
