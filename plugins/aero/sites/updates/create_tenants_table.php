<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_tenants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->comment('SiteDefinition ID de OctoberCMS');
            $table->foreignId('root_domain_id')->constrained('aero_sites_root_domains')->restrictOnDelete();
            $table->string('name');
            $table->string('handle')->unique()->comment('Slug único global, ej: miclinica');
            $table->string('niche_type')->default('generic');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('primary_color', 7)->default('#6366f1')->comment('Hex color');
            $table->timestamps();
            $table->softDeletes();

            $table->index('site_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_tenants');
    }
};
