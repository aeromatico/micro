<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Un cobro puede agruparse en varias listas a la vez (relación
 * `contactLists`, tabla pivote aero_crm_collection_item_contact_list).
 * `contact_list_id` queda como columna legada, ahora opcional, y sus valores
 * se backfillean hacia el pivote para no perder el histórico.
 *
 * Los nombres de constraint del pivote van explícitos y cortos: los que
 * autogenera Laravel superan los 64 caracteres de MySQL.
 */
return new class extends Migration
{
    protected const ITEMS = 'aero_crm_collection_items';
    protected const PIVOT = 'aero_crm_collection_item_contact_list';

    public function up(): void
    {
        $this->createPivotTable();
        $this->makeContactListIdNullable();
        $this->backfillLists();
    }

    protected function createPivotTable(): void
    {
        if (Schema::hasTable(self::PIVOT)) {
            return;
        }

        Schema::create(self::PIVOT, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_item_id');
            $table->unsignedBigInteger('contact_list_id');
            $table->timestamps();

            $table->foreign('collection_item_id', 'crm_col_item_list_item_fk')
                ->references('id')->on(self::ITEMS)->cascadeOnDelete();

            $table->foreign('contact_list_id', 'crm_col_item_list_list_fk')
                ->references('id')->on('aero_crm_contact_lists')->cascadeOnDelete();

            $table->unique(['collection_item_id', 'contact_list_id'], 'crm_col_item_list_unique');
        });
    }

    protected function makeContactListIdNullable(): void
    {
        if (!Schema::hasColumn(self::ITEMS, 'contact_list_id')) {
            return;
        }

        Schema::table(self::ITEMS, function (Blueprint $table) {
            $table->unsignedBigInteger('contact_list_id')->nullable()->change();
        });
    }

    protected function backfillLists(): void
    {
        if (!Schema::hasTable(self::PIVOT)) {
            return;
        }

        Db::table(self::ITEMS)
            ->whereNotNull('contact_list_id')
            ->orderBy('id')
            ->chunk(200, function ($items) {
                $rows = [];
                $now = now();

                foreach ($items as $item) {
                    $rows[] = [
                        'collection_item_id' => $item->id,
                        'contact_list_id'    => $item->contact_list_id,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }

                // El índice único (item, lista) descarta los que ya estaban.
                Db::table(self::PIVOT)->insertOrIgnore($rows);
            });
    }

    /**
     * No hay down(): volver a NOT NULL podría fallar con cobros sin lista
     * única, y el backfill es data que se preserva a propósito.
     */
    public function down(): void
    {
    }
};
