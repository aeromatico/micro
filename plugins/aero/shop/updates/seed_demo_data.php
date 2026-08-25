<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Create shop tables if they don't exist ──────────────────
        if (!Schema::hasTable('aero_shop_currencies')) {
            Schema::create('aero_shop_currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name');
                $table->string('symbol', 10);
                $table->tinyInteger('decimal_places')->unsigned()->default(2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('aero_shop_collections')) {
            Schema::create('aero_shop_collections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('aero_shop_collections')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('aero_shop_products')) {
            Schema::create('aero_shop_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
                $table->foreignId('collection_id')->nullable()->constrained('aero_shop_collections')->nullOnDelete();
                $table->string('type')->default('physical');
                $table->string('name');
                $table->string('slug');
                $table->longText('description')->nullable();
                $table->string('sku')->nullable();
                $table->boolean('has_variants')->default(false);
                $table->decimal('base_price', 14, 4)->default(0);
                $table->decimal('compare_at_price', 14, 4)->nullable();
                $table->decimal('cost_price', 14, 4)->nullable();
                $table->unsignedInteger('weight_grams')->nullable();
                $table->boolean('requires_shipping')->default(true);
                $table->boolean('track_inventory')->default(true);
                $table->unsignedInteger('stock_quantity')->default(0);
                $table->boolean('allow_backorder')->default(false);
                $table->string('status')->default('draft');
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->string('seo_title')->nullable();
                $table->string('seo_description')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'collection_id']);
            });
        }

        if (!Schema::hasTable('aero_shop_product_collection')) {
            Schema::create('aero_shop_product_collection', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('aero_shop_products')->cascadeOnDelete();
                $table->foreignId('collection_id')->constrained('aero_shop_collections')->cascadeOnDelete();
                $table->unique(['product_id', 'collection_id']);
            });
        }

        if (!Schema::hasTable('aero_shop_settings')) {
            Schema::create('aero_shop_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->unique()->constrained('aero_sites_tenants')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(false);
                $table->foreignId('base_currency_id')->nullable()->constrained('aero_shop_currencies')->nullOnDelete();
                $table->boolean('inventory_tracking_enabled')->default(true);
                $table->boolean('guest_checkout_enabled')->default(true);
                $table->string('order_number_prefix')->nullable();
                $table->unsignedInteger('order_number_sequence')->default(0);
                $table->unsignedInteger('low_stock_threshold')->nullable();
                $table->timestamps();
            });
        }

        // ── 2. Seed currencies ─────────────────────────────────────────
        $currencies = [
            ['code' => 'BOB', 'name' => 'Boliviano', 'symbol' => 'Bs', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'Dólar estadounidense', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
        ];
        foreach ($currencies as $c) {
            DB::table('aero_shop_currencies')->updateOrInsert(
                ['code' => $c['code']],
                $c + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $tenantId = DB::table('aero_sites_tenants')->orderBy('id')->value('id');
        if (!$tenantId) {
            return;
        }

        // Enable shop for tenant
        $bobId = DB::table('aero_shop_currencies')->where('code', 'BOB')->value('id');
        DB::table('aero_shop_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'is_enabled'              => true,
                'base_currency_id'        => $bobId,
                'inventory_tracking_enabled' => true,
                'guest_checkout_enabled'  => true,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]
        );

        // ── 3. Unsplash helper ─────────────────────────────────────────
        $row = DB::table('system_settings')->where('item', 'aero_sites_settings')->value('value');
        $key = null;
        if ($row) {
            $decoded = json_decode($row, true);
            $key = $decoded['unsplash_access_key'] ?? null;
        }

        $imageCache = [];
        $fetchImage = function (string $keywords) use ($key, &$imageCache) {
            $hash = md5(strtolower(trim($keywords)));
            if (isset($imageCache[$hash])) {
                return $imageCache[$hash];
            }

            if (!$key) {
                $label = rawurlencode(mb_substr($keywords, 0, 30));
                $url = "https://placehold.co/1200x800/e2e8f0/94a3b8?text={$label}";
                $imageCache[$hash] = $url;
                return $url;
            }

            try {
                $response = Http::withHeaders([
                    'Authorization'  => 'Client-ID ' . $key,
                    'Accept-Version' => 'v1',
                ])->timeout(10)->get('https://api.unsplash.com/search/photos', [
                    'query'          => $keywords,
                    'per_page'       => 1,
                    'orientation'    => 'landscape',
                    'content_filter' => 'high',
                ]);

                if ($response->successful()) {
                    $photo = $response->json('results.0');
                    $url = $photo['urls']['regular'] ?? $photo['urls']['small'] ?? null;
                    if ($url) {
                        $imageCache[$hash] = $url;
                        return $url;
                    }
                }
            } catch (\Exception $e) {
                // Fall through to placeholder
            }

            $label = rawurlencode(mb_substr($keywords, 0, 30));
            $url = "https://placehold.co/1200x800/e2e8f0/94a3b8?text={$label}";
            $imageCache[$hash] = $url;
            return $url;
        };

        // ── 4. Collections ─────────────────────────────────────────────
        $collections = [
            ['name' => 'Bebidas', 'slug' => 'bebidas', 'description' => 'Refrescos, jugos, néctares y bebidas naturales.', 'image_kw' => 'cold drinks beverages assortment', 'sort_order' => 1],
            ['name' => 'Snacks', 'slug' => 'snacks', 'description' => 'Botanas, papas, galletas y aperitivos.', 'image_kw' => 'snacks chips appetizers table', 'sort_order' => 2],
            ['name' => 'Lácteos', 'slug' => 'lacteos', 'description' => 'Leche, yogurt, queso y mantequilla.', 'image_kw' => 'dairy products milk cheese yogurt', 'sort_order' => 3],
            ['name' => 'Panadería', 'slug' => 'panaderia', 'description' => 'Pan fresco, tortillas, bollería artesanal.', 'image_kw' => 'bakery bread fresh artisan', 'sort_order' => 4],
            ['name' => 'Frutas y Verduras', 'slug' => 'frutas-y-verduras', 'description' => 'Productos frescos del campo.', 'image_kw' => 'fresh fruits vegetables market', 'sort_order' => 5],
            ['name' => 'Abarrotes', 'slug' => 'abarrotes', 'description' => 'Productos de despensa, aceites, conservas y más.', 'image_kw' => 'grocery store products pantry', 'sort_order' => 6],
        ];

        $collectionIds = [];
        foreach ($collections as $col) {
            $imageUrl = $fetchImage($col['image_kw']);
            $existingId = DB::table('aero_shop_collections')->where('tenant_id', $tenantId)->where('slug', $col['slug'])->value('id');
            if ($existingId) {
                $collectionIds[$col['slug']] = $existingId;
                continue;
            }
            $id = DB::table('aero_shop_collections')->insertGetId([
                'tenant_id'    => $tenantId,
                'name'         => $col['name'],
                'slug'         => $col['slug'],
                'description'  => $col['description'],
                'is_active'    => true,
                'sort_order'   => $col['sort_order'],
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $collectionIds[$col['slug']] = $id;

            // Store image URL in image cache for reference
            DB::table('aero_sites_image_cache')->updateOrInsert(
                ['keywords_hash' => md5('collection_' . $col['slug'])],
                [
                    'keywords'    => 'collection:' . $col['slug'],
                    'url'         => $imageUrl,
                    'attribution' => null,
                    'provider'    => 'unsplash',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }

        // ── 5. Products ────────────────────────────────────────────────
        $products = [
            // Bebidas (3)
            ['collection' => 'bebidas', 'name' => 'Jugo de Naranja 1L', 'slug' => 'jugo-de-naranja-1l', 'description' => 'Jugo de naranja natural 100%, sin azúcar added. Fresco y refrescante.', 'sku' => 'BEB-001', 'base_price' => 8.50, 'compare' => 10.00, 'stock' => 150, 'weight' => 1050, 'featured' => true, 'image_kw' => 'orange juice glass fresh'],
            ['collection' => 'bebidas', 'name' => 'Agua Mineral 500ml', 'slug' => 'agua-mineral-500ml', 'description' => 'Agua mineral natural sin gas, botella de 500ml.', 'sku' => 'BEB-002', 'base_price' => 3.00, 'compare' => null, 'stock' => 300, 'weight' => 520, 'featured' => false, 'image_kw' => 'mineral water bottle clean'],
            ['collection' => 'bebidas', 'name' => 'Gaseosa Cola 2L', 'slug' => 'gaseosa-cola-2l', 'description' => 'Gaseosa sabor cola, botella familiar de 2 litros.', 'sku' => 'BEB-003', 'base_price' => 6.00, 'compare' => 7.50, 'stock' => 200, 'weight' => 2050, 'featured' => false, 'image_kw' => 'cola soda drink bottle'],
            // Snacks (2)
            ['collection' => 'snacks', 'name' => 'Papas Fritas Clásicas 150g', 'slug' => 'papas-fritas-clasicas-150g', 'description' => 'Papas fritas sabor clásico, bolsa familiar de 150g.', 'sku' => 'SNK-001', 'base_price' => 5.50, 'compare' => 6.50, 'stock' => 120, 'weight' => 160, 'featured' => true, 'image_kw' => 'potato chips snack bag'],
            ['collection' => 'snacks', 'name' => 'Galletas de Chocolate 200g', 'slug' => 'galletas-de-chocolate-200g', 'description' => 'Galletas crujientes con chips de chocolate, paquete de 200g.', 'sku' => 'SNK-002', 'base_price' => 7.00, 'compare' => null, 'stock' => 80, 'weight' => 210, 'featured' => false, 'image_kw' => 'chocolate cookies biscuits'],
            // Lácteos (2)
            ['collection' => 'lacteos', 'name' => 'Leche Entera 1L', 'slug' => 'leche-entera-1l', 'description' => 'Leche entera pasteurizada, envase de 1 litro.', 'sku' => 'LAC-001', 'base_price' => 5.00, 'compare' => null, 'stock' => 100, 'weight' => 1030, 'featured' => true, 'image_kw' => 'fresh milk bottle dairy'],
            ['collection' => 'lacteos', 'name' => 'Yogurt Natural 500g', 'slug' => 'yogurt-natural-500g', 'description' => 'Yogurt natural cremoso, tarro de 500g.', 'sku' => 'LAC-002', 'base_price' => 6.50, 'compare' => 8.00, 'stock' => 60, 'weight' => 520, 'featured' => false, 'image_kw' => 'yogurt cup natural creamy'],
            // Panadería (2)
            ['collection' => 'panaderia', 'name' => 'Pan Frances 12un', 'slug' => 'pan-frances-12un', 'description' => 'Docena de panes franceses recién horneados.', 'sku' => 'PAN-001', 'base_price' => 4.50, 'compare' => null, 'stock' => 200, 'weight' => 600, 'featured' => true, 'image_kw' => 'french bread baguette fresh baked'],
            ['collection' => 'panaderia', 'name' => 'Tortillas de Maíz 1kg', 'slug' => 'tortillas-de-maiz-1kg', 'description' => 'Tortillas de maíz artesanales, paquete de 1kg.', 'sku' => 'PAN-002', 'base_price' => 6.00, 'compare' => 7.00, 'stock' => 80, 'weight' => 1000, 'featured' => false, 'image_kw' => 'corn tortillas traditional'],
            // Frutas y Verduras (2)
            ['collection' => 'frutas-y-verduras', 'name' => 'Manzanas Rojas 1kg', 'slug' => 'manzanas-rojas-1kg', 'description' => 'Manzanas rojas frescas, selección premium, 1kg.', 'sku' => 'FVV-001', 'base_price' => 12.00, 'compare' => 14.00, 'stock' => 50, 'weight' => 1000, 'featured' => true, 'image_kw' => 'red apples fresh fruit'],
            ['collection' => 'frutas-y-verduras', 'name' => 'Tomates 1kg', 'slug' => 'tomates-1kg', 'description' => 'Tomates maduros frescos, ideal para ensaladas y guisos.', 'sku' => 'FVV-002', 'base_price' => 8.00, 'compare' => null, 'stock' => 70, 'weight' => 1000, 'featured' => false, 'image_kw' => 'fresh tomatoes red vegetable'],
            // Abarrotes (1)
            ['collection' => 'abarrotes', 'name' => 'Aceite de Oliva 500ml', 'slug' => 'aceite-de-oliva-500ml', 'description' => 'Aceite de oliva extra virgen, botella de 500ml.', 'sku' => 'ABR-001', 'base_price' => 18.00, 'compare' => 22.00, 'stock' => 40, 'weight' => 520, 'featured' => true, 'image_kw' => 'olive oil bottle cooking'],
        ];

        foreach ($products as $p) {
            $imageUrl = $fetchImage($p['image_kw']);
            $colId = $collectionIds[$p['collection']] ?? null;

            $existingId = DB::table('aero_shop_products')->where('tenant_id', $tenantId)->where('slug', $p['slug'])->value('id');
            if ($existingId) {
                continue;
            }

            $productId = DB::table('aero_shop_products')->insertGetId([
                'tenant_id'        => $tenantId,
                'collection_id'    => $colId,
                'type'             => 'physical',
                'name'             => $p['name'],
                'slug'             => $p['slug'],
                'description'      => $p['description'],
                'sku'              => $p['sku'],
                'has_variants'     => false,
                'base_price'       => $p['base_price'],
                'compare_at_price' => $p['compare'],
                'cost_price'       => null,
                'weight_grams'     => $p['weight'],
                'requires_shipping'=> true,
                'track_inventory'  => true,
                'stock_quantity'   => $p['stock'],
                'allow_backorder'  => false,
                'status'           => 'active',
                'is_featured'      => $p['featured'],
                'published_at'     => Carbon::now(),
                'seo_title'        => $p['name'],
                'seo_description'  => mb_substr($p['description'], 0, 160),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Pivot product <-> collection
            if ($colId) {
                DB::table('aero_shop_product_collection')->updateOrInsert(
                    ['product_id' => $productId, 'collection_id' => $colId],
                    []
                );
            }

            // Store product image URL in image cache
            DB::table('aero_sites_image_cache')->updateOrInsert(
                ['keywords_hash' => md5('product_' . $p['slug'])],
                [
                    'keywords'    => 'product:' . $p['slug'],
                    'url'         => $imageUrl,
                    'attribution' => null,
                    'provider'    => 'unsplash',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $tenantId = DB::table('aero_sites_tenants')->orderBy('id')->value('id');
        if (!$tenantId) {
            return;
        }
        DB::table('aero_shop_products')->where('tenant_id', $tenantId)->delete();
        DB::table('aero_shop_collections')->where('tenant_id', $tenantId)->delete();
        DB::table('aero_shop_settings')->where('tenant_id', $tenantId)->delete();
    }
};
