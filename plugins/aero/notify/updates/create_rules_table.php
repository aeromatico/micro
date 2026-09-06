<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Regla de entrega: evento x audiencia x canal x plantilla x condiciones.
 * Toda la matriz que en el sistema de referencia vivía en dos booleanos y en
 * mapas hardcodeados de PHP pasa a ser una fila de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_notify_rules', function (Blueprint $table) {
            $table->id();

            // 0 = regla por defecto de la plataforma (ver nota en templates).
            $table->unsignedBigInteger('tenant_id')->default(0);

            $table->foreignId('event_id')
                ->constrained('aero_notify_events')->cascadeOnDelete();

            $table->string('audience', 40);
            $table->json('audience_filter')->nullable();
            $table->string('channel', 30);

            $table->foreignId('template_id')->nullable()
                ->constrained('aero_notify_templates')->nullOnDelete();

            // AND lógico: [{"var":"amount","op":">=","value":100}]
            $table->json('conditions')->nullable();

            $table->unsignedInteger('delay_seconds')->default(0);
            $table->unsignedInteger('dedup_window_min')->default(0);
            $table->unsignedInteger('digest_window_min')->default(0);
            $table->string('digest_key_expr', 100)->nullable();
            $table->unsignedSmallInteger('max_per_hour')->nullable();

            // Override de events.priority.
            $table->unsignedTinyInteger('priority')->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'event_id', 'audience', 'channel'], 'notify_rule_unique');
            $table->index(['event_id', 'is_enabled']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_notify_rules');
    }
};
