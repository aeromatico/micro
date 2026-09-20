<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Respuestas rápidas por ámbito (tenant_id NULL = plataforma), invocables con /atajo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('area', 30)->default('general');
            $table->string('title');
            $table->string('shortcut', 40);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'shortcut']);
            $table->index(['tenant_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_quick_replies');
    }
};
