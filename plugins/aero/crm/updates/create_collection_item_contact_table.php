<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Contactos adicionales de un cobro: además del deudor principal
 * (`aero_crm_collection_items.contact_id`) se puede sumar a mano una o
 * varias personas que también reciben el recordatorio (familiar, encargado
 * de pagos, etc.), sin que estén atadas a la lista que agrupa el cobro.
 *
 * Los nombres de constraint van explícitos y cortos: el que Laravel genera
 * para el índice único supera los 64 caracteres de MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_crm_collection_item_contact')) {
            return;
        }

        Schema::create('aero_crm_collection_item_contact', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_item_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();

            $table->foreign('collection_item_id', 'crm_col_item_contact_item_fk')
                ->references('id')->on('aero_crm_collection_items')->cascadeOnDelete();

            $table->foreign('contact_id', 'crm_col_item_contact_contact_fk')
                ->references('id')->on('aero_crm_contacts')->cascadeOnDelete();

            $table->unique(['collection_item_id', 'contact_id'], 'crm_col_item_contact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_collection_item_contact');
    }
};
