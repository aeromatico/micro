<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Crea la tabla `aero_masterads_insights`.
 *
 * Almacena las métricas diarias (impressions, clicks, spend, conversions,
 * video_views) por entidad de la jerarquía Meta (campaign | adset | ad).
 * El diseño es time-series: una fila por (entity_type, entity_id, date).
 *
 * No usa `timestamps()`: sólo `created_at` para registrar el momento de
 * ingest; `updated_at` se omite porque los upserts del job de sincronización
 * sobrescriben in-place y no requieren trazabilidad de modificación.
 *
 * Índices:
 *  - UNIQUE(entity_type, entity_id, date) — clave idempotente del upsert
 *    ejecutado por `SyncInsightsJob`. Es la garantía estructural de la
 *    Propiedad P4 (Sync Idempotency): re-ejecutar el sync sobre el mismo
 *    rango no genera filas duplicadas.
 *  - INDEX(entity_type, entity_id, date) — soporta las consultas de
 *    lookback del `MetricsAggregator` (rango de N días para una entidad).
 *
 * Validates Requirements 4.2, 4.5, 14.4, 17.4, 17.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_masterads_insights', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('entity_type', ['campaign', 'adset', 'ad']);
            $table->unsignedBigInteger('entity_id');
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('spend', 12, 4)->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->unsignedBigInteger('video_views')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['entity_type', 'entity_id', 'date'],
                'aero_masterads_insights_entity_date_unique'
            );

            $table->index(
                ['entity_type', 'entity_id', 'date'],
                'aero_masterads_insights_entity_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_masterads_insights');
    }
};
