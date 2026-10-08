<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Edición propuesta por la IA sobre un artículo ya publicado: se guarda aparte
 * y no cambia lo que ve el público hasta que alguien la aprueba.
 * `pending_plugin_version` evita que el watcher de docs-sync vuelva a pedir la
 * misma actualización mientras espera revisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('aero_docs_articles', 'pending_content')) {
            return;
        }

        Schema::table('aero_docs_articles', function (Blueprint $table) {
            $table->longText('pending_content')->nullable();
            $table->string('pending_plugin_version', 32)->nullable();
            $table->timestamp('pending_at')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('aero_docs_articles', 'pending_content')) {
            return;
        }

        Schema::table('aero_docs_articles', function (Blueprint $table) {
            $table->dropColumn(['pending_content', 'pending_plugin_version', 'pending_at']);
        });
    }
};
