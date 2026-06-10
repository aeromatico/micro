<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_seo_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('title_format')->default('%s | {name}');
            $table->string('default_description', 500)->nullable();
            $table->string('google_analytics_id', 50)->nullable();
            $table->text('robots_txt')->nullable();
            $table->boolean('sitemap_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_seo_configs');
    }
};
