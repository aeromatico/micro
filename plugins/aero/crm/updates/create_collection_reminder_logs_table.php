<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Los nombres de constraint van explícitos y cortos: los que genera Laravel a
 * partir de tabla+columna superan los 64 caracteres que admite MySQL y hacen
 * fallar la migración a medias (la tabla queda creada, la FK no).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_collection_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_item_id');
            $table->unsignedBigInteger('collection_reminder_rule_id');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->foreign('collection_item_id', 'crm_reminder_log_item_fk')
                ->references('id')->on('aero_crm_collection_items')->cascadeOnDelete();

            $table->foreign('collection_reminder_rule_id', 'crm_reminder_log_rule_fk')
                ->references('id')->on('aero_crm_collection_reminder_rules')->cascadeOnDelete();

            // Es lo que impide repetir un paso de la cascada sobre el mismo cobro.
            $table->unique(['collection_item_id', 'collection_reminder_rule_id'], 'crm_reminder_log_item_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_collection_reminder_logs');
    }
};
