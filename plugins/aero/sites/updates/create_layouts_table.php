<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_layouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->string('mode', 20)->default('default');
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->longText('custom_html')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('aero_sites_tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_layouts');
    }
};
