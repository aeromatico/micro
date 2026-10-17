<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_shortcuts_items')) {
            return;
        }

        Schema::create('aero_shortcuts_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('label', 60);
            $table->string('kind', 10)->default('list'); // list | form | url
            $table->string('target')->nullable();         // ruta del backend (list/form)
            $table->string('url', 500)->nullable();       // URL personalizada (url)
            $table->string('icon', 40)->default('icon-star');
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'sort_order']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shortcuts_items');
    }
};
