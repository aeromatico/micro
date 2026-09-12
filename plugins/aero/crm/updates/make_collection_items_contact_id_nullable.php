<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * El cobro ya no se ata a un único contacto: los destinatarios viven en la
 * tabla pivote `aero_crm_collection_item_contact` (relación `recipients`).
 * `contact_id` queda como columna legada, ahora opcional, y su valor se
 * backfillea hacia el pivote para que los cobros creados antes de este cambio
 * sigan enviando recordatorio.
 */
return new class extends Migration
{
    protected const ITEMS = 'aero_crm_collection_items';
    protected const PIVOT = 'aero_crm_collection_item_contact';

    public function up(): void
    {
        $this->makeContactIdNullable();
        $this->backfillRecipients();
    }

    protected function makeContactIdNullable(): void
    {
        if (!Schema::hasColumn(self::ITEMS, 'contact_id')) {
            return;
        }

        Schema::table(self::ITEMS, function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->change();
        });
    }

    protected function backfillRecipients(): void
    {
        if (!Schema::hasTable(self::PIVOT)) {
            return;
        }

        Db::table(self::ITEMS)
            ->whereNotNull('contact_id')
            ->orderBy('id')
            ->chunk(200, function ($items) {
                $rows = [];
                $now = now();

                foreach ($items as $item) {
                    $rows[] = [
                        'collection_item_id' => $item->id,
                        'contact_id'         => $item->contact_id,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }

                // El índice único (item, contacto) descarta los que ya estaban.
                Db::table(self::PIVOT)->insertOrIgnore($rows);
            });
    }

    /**
     * No hay down(): volver a NOT NULL podría fallar con cobros sin contacto
     * único, y el backfill es data que se preserva a propósito.
     */
    public function down(): void
    {
    }
};
