<?php

use October\Rain\Database\Updates\Migration;

/**
 * Índice por tenant_id en tablas que se consultan siempre filtradas por tenant.
 * Re-ejecutable: no hace nada si la tabla o el índice ya existen.
 */
return new class extends Migration
{
    protected array $tables = [
        'aero_tracking_feed_events',
        'aero_tracking_positions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (!Schema::hasTable($name) || !Schema::hasColumn($name, 'tenant_id') || Schema::hasIndex($name, ['tenant_id'])) {
                continue;
            }
            Schema::table($name, function ($table) {
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        // Los índices son inocuos: no se quitan.
    }
};
