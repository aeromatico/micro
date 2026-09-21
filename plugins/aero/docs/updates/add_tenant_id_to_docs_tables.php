<?php

use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Un solo sistema de docs para varios sitios. tenant_id NULL = documentación de
 * la plataforma (sitio principal); con valor = la del tenant. Todo lo existente
 * queda como plataforma. El slug deja de ser único global: lo es por ámbito,
 * vía tenant_key (tenant_id o 0) porque un UNIQUE con NULL no bloquea duplicados.
 *
 * tenant_key es VIRTUAL, no STORED: en MySQL 8 agregar una columna generada
 * STORED que referencia tenant_id (con FK a aero_sites_tenants) falla con
 * "Cannot add foreign key constraint" (1215) durante el rebuild de la tabla.
 * Una virtual sirve igual para el índice único.
 *
 * Idempotente: convive con instalaciones nuevas (esquema ya presente) y con
 * instalaciones a medio migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['aero_docs_categories', 'aero_docs_articles'] as $name) {
            $this->dropIndexIfExists($name, $name . '_slug_unique');

            if (!Schema::hasColumn($name, 'tenant_id')) {
                Schema::table($name, function ($table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')
                        ->constrained('aero_sites_tenants')->cascadeOnDelete();
                });
            }

            if (!Schema::hasColumn($name, 'tenant_key')) {
                Schema::table($name, function ($table) {
                    $table->unsignedBigInteger('tenant_key')->virtualAs('ifnull(tenant_id, 0)');
                });
            }

            $this->addUniqueIfMissing($name, ['tenant_key', 'slug'], $name . '_tenant_key_slug_unique');
        }
    }

    public function down(): void
    {
        foreach (['aero_docs_categories', 'aero_docs_articles'] as $name) {
            $this->dropIndexIfExists($name, $name . '_tenant_key_slug_unique');

            if (Schema::hasColumn($name, 'tenant_key')) {
                Schema::table($name, function ($table) {
                    $table->dropColumn('tenant_key');
                });
            }
            if (Schema::hasColumn($name, 'tenant_id')) {
                Schema::table($name, function ($table) {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }

            $this->addUniqueIfMissing($name, ['slug'], $name . '_slug_unique');
        }
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            \DB::statement("alter table `{$table}` drop index `{$index}`");
        }
    }

    protected function addUniqueIfMissing(string $table, array $columns, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $cols = implode(',', array_map(fn ($c) => "`{$c}`", $columns));
        \DB::statement("alter table `{$table}` add unique `{$index}` ({$cols})");
    }

    protected function indexExists(string $table, string $index): bool
    {
        return \DB::table('information_schema.statistics')
            ->where('table_schema', \DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
