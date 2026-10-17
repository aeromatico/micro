<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_rgpd_configs')) {
            return;
        }

        Schema::create('aero_rgpd_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->unique(); // NULL = plataforma
            $table->boolean('is_enabled')->default(false);
            $table->string('title', 120)->nullable();
            $table->text('message')->nullable();
            $table->string('policy_url', 500)->nullable();
            $table->string('accept_label', 40)->nullable();
            $table->string('reject_label', 40)->nullable();
            $table->string('customize_label', 40)->nullable();
            $table->string('position', 20)->default('bottom');
            $table->boolean('use_analytics')->default(true);
            $table->boolean('use_marketing')->default(true);
            $table->boolean('keep_log')->default(true);
            $table->unsignedInteger('version')->default(1); // subirla vuelve a pedir consentimiento
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_rgpd_configs');
    }
};
