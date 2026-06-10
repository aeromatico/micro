<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_root_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique()->comment('ej: micro.clouds.com.bo');
            $table->string('label')->comment('Nombre amigable');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_root_domains');
    }
};
