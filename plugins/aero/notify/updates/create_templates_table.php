<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Plantillas por canal e idioma. tenant_id = 0 es la plantilla global; una fila
 * con tenant_id la sobreescribe. Sustituye a los template_code sueltos sin FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_notify_templates', function (Blueprint $table) {
            $table->id();

            // 0 = global / plataforma. NOT NULL a propósito: con NULL los índices
            // UNIQUE no colisionan en MySQL y se duplicarían las filas globales.
            $table->unsignedBigInteger('tenant_id')->default(0);

            $table->foreignId('event_id')->nullable()
                ->constrained('aero_notify_events')->cascadeOnDelete();

            $table->string('code', 120)->unique();
            $table->string('channel', 30);
            $table->string('locale', 5)->default('es');

            $table->string('subject')->nullable();
            $table->mediumText('body');
            $table->mediumText('body_html')->nullable();
            $table->string('format', 10)->default('twig');
            $table->string('layout', 60)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'event_id', 'channel', 'locale'], 'notify_tpl_unique');
            $table->index('tenant_id');
            $table->index(['channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_notify_templates');
    }
};
