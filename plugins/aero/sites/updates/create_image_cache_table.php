<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_image_cache', function (Blueprint $table) {
            $table->id();
            $table->string('keywords_hash', 32)->unique();
            $table->string('keywords');
            $table->string('url', 1000);
            $table->string('attribution')->nullable();
            $table->string('provider', 30)->default('unsplash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_image_cache');
    }
};
