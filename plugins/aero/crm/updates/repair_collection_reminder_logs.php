<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Repara las instalaciones donde `create_collection_reminder_logs_table.php`
 * murió a la mitad: los nombres de constraint autogenerados superaban los 64
 * caracteres de MySQL, así que la tabla quedó creada pero SIN la foreign key
 * de `collection_reminder_rule_id` ni el índice único, y October igual marcó
 * la versión como aplicada — es decir, nunca la reintentaría.
 *
 * El índice único no es cosmético: es lo que impide que un mismo paso de la
 * cascada de recordatorios se dispare dos veces sobre el mismo cobro.
 */
return new class extends Migration
{
    protected const TABLE = 'aero_crm_collection_reminder_logs';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->dedupeLogs();

        if (!$this->hasIndex('crm_reminder_log_item_rule_unique')) {
            Db::statement('ALTER TABLE `' . self::TABLE . '`
                ADD UNIQUE `crm_reminder_log_item_rule_unique` (`collection_item_id`, `collection_reminder_rule_id`)');
        }

        if (!$this->hasForeignKey('collection_reminder_rule_id')) {
            Db::statement('ALTER TABLE `' . self::TABLE . '`
                ADD CONSTRAINT `crm_reminder_log_rule_fk`
                FOREIGN KEY (`collection_reminder_rule_id`)
                REFERENCES `aero_crm_collection_reminder_rules` (`id`) ON DELETE CASCADE');
        }
    }

    /**
     * No hay down(): esto repara un estado roto, revertirlo sería volver a él.
     */
    public function down(): void
    {
    }

    /**
     * Una fila huérfana (regla ya borrada) impediría crear la FK, y las
     * duplicadas impedirían el índice único. Se limpian antes.
     */
    protected function dedupeLogs(): void
    {
        Db::table(self::TABLE)
            ->whereNotIn('collection_reminder_rule_id', Db::table('aero_crm_collection_reminder_rules')->pluck('id'))
            ->delete();

        $duplicates = Db::table(self::TABLE)
            ->select('collection_item_id', 'collection_reminder_rule_id')
            ->groupBy('collection_item_id', 'collection_reminder_rule_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keepId = Db::table(self::TABLE)
                ->where('collection_item_id', $duplicate->collection_item_id)
                ->where('collection_reminder_rule_id', $duplicate->collection_reminder_rule_id)
                ->min('id');

            Db::table(self::TABLE)
                ->where('collection_item_id', $duplicate->collection_item_id)
                ->where('collection_reminder_rule_id', $duplicate->collection_reminder_rule_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    protected function hasIndex(string $name): bool
    {
        return count(Db::select(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [self::TABLE, $name]
        )) > 0;
    }

    protected function hasForeignKey(string $column): bool
    {
        return count(Db::select(
            'SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [self::TABLE, $column]
        )) > 0;
    }
};
