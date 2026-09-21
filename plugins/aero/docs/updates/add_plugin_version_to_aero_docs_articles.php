<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Versión del plugin documentada en cada artículo, para detectar
 * documentación desactualizada. Se llama `plugin_version` —no `version`—
 * porque `aero_docs_articles.version` ya existe: es el número de revisión del
 * artículo (ver add_engagement_and_versions.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('aero_docs_articles', 'plugin_version')) {
            return;
        }

        Schema::table('aero_docs_articles', function (Blueprint $table) {
            $table->string('plugin_version', 32)->nullable()->after('category_id')
                ->comment('Versión del plugin documentada (para detectar docs desactualizadas)');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('aero_docs_articles', 'plugin_version')) {
            return;
        }

        Schema::table('aero_docs_articles', function (Blueprint $table) {
            $table->dropColumn('plugin_version');
        });
    }
};
