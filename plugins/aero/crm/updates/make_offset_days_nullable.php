<?php

use October\Rain\Database\Updates\Migration;

/**
 * 1.2.2 sustituyó `offset_days` por `start_days_before` + `frequency_days`.
 * El modelo ya no lo puebla (default null) y no está en el formulario, pero la
 * columna quedó NOT NULL sin default: al crear una regla el INSERT fallaba con
 * "Column 'offset_days' cannot be null".
 */
return new class extends Migration
{
    protected const TABLE = 'aero_crm_collection_reminder_rules';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->isColumnNotNull('offset_days')) {
            Db::statement('ALTER TABLE `' . self::TABLE . '` MODIFY `offset_days` INT NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            Db::statement('ALTER TABLE `' . self::TABLE . '` MODIFY `offset_days` INT NOT NULL');
        }
    }

    protected function isColumnNotNull(string $column): bool
    {
        return count(Db::select(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND COLUMN_NAME = ? AND IS_NULLABLE = "NO" LIMIT 1',
            [self::TABLE, $column]
        )) > 0;
    }
};
