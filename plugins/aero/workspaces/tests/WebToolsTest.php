<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Classes\AgentRunner;
use Aero\Workspaces\Classes\WebTools;
use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Illuminate\Support\Facades\DB;
use PluginTestCase;

/**
 * Herramientas web_* de Iván: exigen plan acordado, lo nuevo nace en borrador,
 * lo publicado pide confirmación, todo se puede deshacer y nada cruza tenants.
 * Las tablas de Sites/Shop se crean mínimas (no se arrancan esos plugins).
 */
class WebToolsTest extends PluginTestCase
{
    protected const PLAN = 'Crear la página Nosotros con hero y preguntas frecuentes.';

    public function setUp(): void
    {
        parent::setUp();

        \Schema::create('aero_sites_tenants', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        DB::table('aero_sites_tenants')->insert([['id' => 1, 'name' => 'Uno'], ['id' => 2, 'name' => 'Dos']]);

        \Schema::create('aero_sites_pages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('title');
            $t->string('slug');
            $t->longText('content')->nullable();
            $t->longText('puck_data')->nullable();
            $t->string('content_mode')->default('puck');
            $t->boolean('is_placeholder')->default(false);
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 500)->nullable();
            $t->string('layout')->default('default');
            $t->boolean('is_published')->default(true);
            $t->integer('sort_order')->default(0);
            $t->boolean('show_in_menu')->default(false);
            $t->longText('menu_positions')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        \Schema::create('aero_shop_settings', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('store_mode')->default('standard');
            $t->longText('restaurant_config')->nullable();
        });

        \Schema::create('aero_shop_collections', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        \Schema::create('aero_shop_products', function ($t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('collection_id')->nullable();
            $t->string('type')->default('physical');
            $t->string('name');
            $t->string('slug')->nullable();
            $t->text('description')->nullable();
            $t->string('sku')->nullable();
            $t->boolean('has_variants')->default(false);
            $t->decimal('base_price', 12, 2)->default(0);
            $t->decimal('compare_at_price', 12, 2)->nullable();
            $t->decimal('cost_price', 12, 2)->nullable();
            $t->integer('weight_grams')->nullable();
            $t->boolean('requires_shipping')->default(true);
            $t->boolean('track_inventory')->default(false);
            $t->integer('stock_quantity')->default(0);
            $t->boolean('allow_backorder')->default(false);
            $t->string('status')->default('draft');
            $t->boolean('is_featured')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->integer('prep_minutes')->nullable();
            $t->integer('min_quantity')->default(1);
            $t->string('barcode')->nullable();
            $t->boolean('is_internal')->default(false);
            $t->string('seo_title')->nullable();
            $t->string('seo_description')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        // Sin renderizador real: lo que se prueba aquí son las reglas de la herramienta.
        WebTools::$renderer = fn (array $puck) => '<section>ok</section>';
    }

    public function tearDown(): void
    {
        WebTools::$renderer = null;
        parent::tearDown();
    }

    protected function blocks(): array
    {
        return [['type' => 'TextBlock', 'props' => ['content' => '<p>Hola</p>']]];
    }

    protected function savePage(array $extra = [], int $tenant = 1): array
    {
        return WebTools::pageSave($extra + ['agreed_plan' => static::PLAN, 'title' => 'Nosotros', 'blocks' => $this->blocks()], $tenant);
    }

    public function testToolsAreOnlyAvailableToAgentsThatDeclareThem(): void
    {
        $staff = new Staff();
        $staff->fill(['name' => 'Iván', 'slug' => 'ivan-t', 'role' => 'Web', 'kind' => 'ai', 'rarity' => 'sr', 'category' => 'web', 'is_active' => true, 'system_prompt' => 'x']);
        $staff->save();

        $this->assertSame([], AgentRunner::toolNames($staff->fresh('skills')), 'sin skill no hay herramientas');

        $skill = Skill::create(['kind' => 'official', 'name' => 'Web', 'slug' => 'web-t', 'description' => 'd', 'body' => 'b', 'tools' => ['web_context', 'web_page_save', 'inexistente']]);
        $staff->skills()->attach($skill->id);

        $this->assertEqualsCanonicalizing(['web_context', 'web_page_save'], AgentRunner::toolNames($staff->fresh('skills')));
    }

    public function testSavingNeedsAnAgreedPlan(): void
    {
        $out = WebTools::pageSave(['title' => 'Nosotros', 'blocks' => $this->blocks()], 1);

        $this->assertFalse($out['saved']);
        $this->assertStringContainsString('agreed_plan', $out['errors'][0]);
        $this->assertSame(0, DB::table('aero_sites_pages')->count());

        $this->assertFalse(WebTools::productSave(['name' => 'X', 'base_price' => 5], 1)['saved']);
    }

    public function testNewPageIsADraftAndFailsClosedWithoutTenant(): void
    {
        $out = $this->savePage();

        $this->assertTrue($out['saved']);
        $this->assertFalse($out['published']);
        $this->assertSame('nosotros', $out['slug']);
        $this->assertSame(0, (int) DB::table('aero_sites_pages')->value('is_published'));
        $this->assertSame(1, (int) DB::table('aero_sites_pages')->value('tenant_id'));

        $this->assertArrayHasKey('error', WebTools::pages([], 0));
        $this->assertArrayHasKey('error', WebTools::pages([], 99));
    }

    public function testInvalidAndUnsafeBlocksAreRejected(): void
    {
        $bad = $this->savePage(['blocks' => [['type' => 'NoExiste', 'props' => []]]]);
        $this->assertFalse($bad['saved']);

        $xss = $this->savePage(['blocks' => [['type' => 'TextBlock', 'props' => ['content' => '<p onclick="x()">Hola</p>']]]]);
        $this->assertFalse($xss['saved']);

        $js = $this->savePage(['blocks' => [['type' => 'Hero', 'props' => ['ctaUrl' => 'javascript:alert(1)']]]]);
        $this->assertFalse($js['saved']);

        $this->assertSame(0, DB::table('aero_sites_pages')->count());
    }

    public function testSlugsAreUniquePerTenantAndReservedOnesRefused(): void
    {
        $this->savePage();
        $second = $this->savePage();
        $this->assertSame('nosotros-2', $second['slug']);

        $other = $this->savePage([], 2);
        $this->assertSame('nosotros', $other['slug'], 'otro tenant puede repetirlo');

        $this->assertFalse($this->savePage(['title' => 'API', 'slug' => 'backend'])['saved']);
    }

    public function testPublishedPageNeedsConfirmAndEverythingCanBeUndone(): void
    {
        $id = $this->savePage()['id'];

        $this->assertFalse(WebTools::pagePublish(['id' => $id, 'published' => true], 1)['confirmed']);
        $this->assertSame(0, (int) DB::table('aero_sites_pages')->where('id', $id)->value('is_published'));

        $this->assertTrue(WebTools::pagePublish(['id' => $id, 'published' => true, 'confirm' => true], 1)['published']);

        // Editar una página publicada sin confirmar: no cambia nada.
        $edit = WebTools::pageSave(['id' => $id, 'agreed_plan' => static::PLAN, 'title' => 'Quiénes somos'], 1);
        $this->assertFalse($edit['saved']);
        $this->assertSame('Nosotros', DB::table('aero_sites_pages')->where('id', $id)->value('title'));

        $this->assertTrue(WebTools::pageSave(['id' => $id, 'agreed_plan' => static::PLAN, 'title' => 'Quiénes somos', 'confirm' => true], 1)['saved']);
        $this->assertSame('Quiénes somos', DB::table('aero_sites_pages')->where('id', $id)->value('title'));

        // Deshacer: primero solo avisa; con confirm restaura.
        $this->assertFalse(WebTools::undo([], 1)['confirmed']);
        $this->assertTrue(WebTools::undo(['confirm' => true], 1)['confirmed']);
        $this->assertSame('Nosotros', DB::table('aero_sites_pages')->where('id', $id)->value('title'));

        // Lo creado y ya publicado no se elimina por undo.
        $created = \Aero\Workspaces\Models\Revision::where('action', 'create')->first();
        $this->assertArrayHasKey('error', WebTools::undo(['revision_id' => $created->id, 'confirm' => true], 1));
    }

    public function testUndoOfACreatedDraftRemovesIt(): void
    {
        $id = $this->savePage()['id'];

        WebTools::undo(['confirm' => true], 1);

        $this->assertNull(\Aero\Sites\Models\Page::find($id));
    }

    public function testOtherTenantsPagesAreInvisibleAndImmutable(): void
    {
        $theirs = $this->savePage([], 2)['id'];

        $this->assertArrayHasKey('error', WebTools::pages(['id' => $theirs], 1));
        $this->assertFalse(WebTools::pageSave(['id' => $theirs, 'agreed_plan' => static::PLAN, 'title' => 'Hackeada', 'confirm' => true], 1)['saved']);
        $this->assertArrayHasKey('error', WebTools::pagePublish(['id' => $theirs, 'published' => true, 'confirm' => true], 1));
        $this->assertSame('Nosotros', DB::table('aero_sites_pages')->where('id', $theirs)->value('title'));
        $this->assertSame([], WebTools::changes([], 1)['changes']);
    }

    public function testProductsAreCreatedAsDraftEvenIfTheModelAsksForActive(): void
    {
        $out = WebTools::productSave(['agreed_plan' => static::PLAN, 'name' => 'Poncho', 'base_price' => 120, 'status' => 'active', 'description' => '<p>Lana</p><script>x</script>'], 1);

        $this->assertFalse($out['saved'], 'una descripción con script se rechaza');

        $ok = WebTools::productSave(['agreed_plan' => static::PLAN, 'name' => 'Poncho', 'base_price' => 120, 'status' => 'active', 'description' => '<p>Lana de alpaca</p>'], 1);

        $this->assertTrue($ok['saved']);
        $this->assertSame('draft', $ok['product']['status']);
        $this->assertSame(120.0, $ok['product']['base_price']);
    }

    public function testActivatingAProductNeedsDataAndConfirmation(): void
    {
        $id = WebTools::productSave(['agreed_plan' => static::PLAN, 'name' => 'Poncho', 'base_price' => 120], 1)['product']['id'];

        $this->assertArrayHasKey('error', WebTools::productStatus(['id' => $id, 'status' => 'active', 'confirm' => true], 1), 'sin descripción no se activa');

        WebTools::productSave(['id' => $id, 'agreed_plan' => static::PLAN, 'description' => 'Poncho de lana'], 1);

        $this->assertFalse(WebTools::productStatus(['id' => $id, 'status' => 'active'], 1)['confirmed']);
        $this->assertSame('draft', DB::table('aero_shop_products')->where('id', $id)->value('status'));

        $this->assertSame('active', WebTools::productStatus(['id' => $id, 'status' => 'active', 'confirm' => true], 1)['status']);

        // Ya activo: editar el precio exige confirmación y se puede deshacer.
        $edit = WebTools::productSave(['id' => $id, 'agreed_plan' => static::PLAN, 'base_price' => 99], 1);
        $this->assertFalse($edit['saved']);
        $this->assertSame(120.0, (float) DB::table('aero_shop_products')->where('id', $id)->value('base_price'));

        WebTools::productSave(['id' => $id, 'agreed_plan' => static::PLAN, 'base_price' => 99, 'confirm' => true], 1);
        $this->assertSame(99.0, (float) DB::table('aero_shop_products')->where('id', $id)->value('base_price'));

        WebTools::undo(['confirm' => true], 1);
        $this->assertSame(120.0, (float) DB::table('aero_shop_products')->where('id', $id)->value('base_price'));
    }

    public function testBatchIsAllOrNothingAndRejectsForeignCollections(): void
    {
        $theirs = WebTools::collectionSave(['agreed_plan' => static::PLAN, 'name' => 'Ajena'], 2)['id'];
        $mine = WebTools::collectionSave(['agreed_plan' => static::PLAN, 'name' => 'Ponchos'], 1)['id'];

        $bad = WebTools::productsCreate(['agreed_plan' => static::PLAN, 'items' => [
            ['name' => 'Bueno', 'base_price' => 10, 'collection_id' => $mine],
            ['name' => 'Malo', 'base_price' => 10, 'collection_id' => $theirs],
        ]], 1);

        $this->assertFalse($bad['saved']);
        $this->assertSame(0, DB::table('aero_shop_products')->count());

        $good = WebTools::productsCreate(['agreed_plan' => static::PLAN, 'items' => [
            ['name' => 'Poncho', 'base_price' => 10, 'collection_id' => $mine],
            ['name' => 'Chullo', 'base_price' => 8],
        ]], 1);

        $this->assertSame(2, $good['created']);
        $this->assertSame(['draft', 'draft'], array_column($good['products'], 'status'));
        $this->assertSame(1, WebTools::collections([], 1)['collections'][0]['products']);
        $this->assertSame(2, WebTools::products([], 1)['total']);
        $this->assertSame(0, WebTools::products([], 2)['total']);
    }

    public function testBatchSizeIsCapped(): void
    {
        $items = array_fill(0, WebTools::MAX_BATCH + 1, ['name' => 'X', 'base_price' => 1]);

        $this->assertFalse(WebTools::productsCreate(['agreed_plan' => static::PLAN, 'items' => $items], 1)['saved']);
    }
}
