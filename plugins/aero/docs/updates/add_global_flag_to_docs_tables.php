<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * `is_global`: el superadmin puede marcar categorías y artículos como globales
 * (documentación de uso general). Se muestran en el sitio de la plataforma y en
 * el centro de ayuda de todos los tenants, tal cual, sin duplicarlos.
 *
 * Idempotente: convive con instalaciones nuevas y existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['aero_docs_categories', 'aero_docs_articles'] as $name) {
            if (!Schema::hasColumn($name, 'is_global')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->boolean('is_global')->default(false)->after('tenant_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['aero_docs_categories', 'aero_docs_articles'] as $name) {
            if (Schema::hasColumn($name, 'is_global')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('is_global');
                });
            }
        }
    }
};
