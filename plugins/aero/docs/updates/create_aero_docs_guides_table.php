<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Guías interactivas (HTML autocontenido) enlazadas a un artículo de docs.
 * `html` es lo publicado; `pending_html` es la propuesta de la IA a la espera
 * de revisión. tenant_key es VIRTUAL por la misma razón que en las demás
 * tablas de docs (ver add_tenant_id_to_docs_tables.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_docs_guides')) {
            return;
        }

        Schema::create('aero_docs_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->boolean('is_global')->default(false);
            $table->string('slug', 150);
            $table->string('title');
            $table->string('plugin', 64)->index();
            $table->string('form_ref', 191)->nullable()->comment('ej. Aero.Pay/bankaccounts/create');
            $table->foreignId('article_id')->nullable()->constrained('aero_docs_articles')->nullOnDelete();
            $table->longText('html')->nullable();
            $table->longText('pending_html')->nullable();
            $table->timestamp('pending_at')->nullable();
            $table->string('html_hash', 40)->nullable();
            $table->string('source_hash', 40)->nullable()->comment('Hash de las fuentes (YAML/modelo) con las que se generó');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('aero_docs_guides', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_key')->virtualAs('ifnull(tenant_id, 0)');
            $table->unique(['tenant_key', 'slug'], 'aero_docs_guides_tenant_key_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_docs_guides');
    }
};
