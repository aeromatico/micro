<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_services_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('aero_services_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('aero_services_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 20)->default('one_time');           // one_time | recurring | project | hourly
            $table->decimal('price', 12, 2)->nullable();               // null = a cotizar
            $table->boolean('price_from')->default(false);             // "desde"
            $table->decimal('setup_fee', 12, 2)->nullable();
            $table->string('currency', 3)->default('BOB');
            $table->string('billing_period', 20)->nullable();          // monthly | quarterly | yearly (solo recurring)
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->json('features')->nullable();
            $table->json('requirements')->nullable();
            $table->json('plugin_links')->nullable();                  // [{plugin, relation, note}]
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_services_services');
        Schema::dropIfExists('aero_services_categories');
    }
};
