<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Reemplaza el nombre del paso por inicio y frecuencia configurables, registra
 * cada fecha programada y agrega el recordatorio especial del vencimiento.
 *
 * Idempotente: en instalaciones donde el script se ejecutó a medias o a mano
 * (columnas ya creadas pero versión sin registrar), `october:up` lo reejecuta
 * sin romperse por columnas/índices duplicados.
 */
return new class extends Migration
{
    protected const RULES_TABLE = 'aero_crm_collection_reminder_rules';
    protected const LOGS_TABLE = 'aero_crm_collection_reminder_logs';

    public function up(): void
    {
        if (Schema::hasTable(self::RULES_TABLE)) {
            Schema::table(self::RULES_TABLE, function (Blueprint $table) {
                if (!Schema::hasColumn(self::RULES_TABLE, 'start_days_before')) {
                    $table->unsignedInteger('start_days_before')->default(5);
                }

                if (!Schema::hasColumn(self::RULES_TABLE, 'frequency_days')) {
                    $table->unsignedInteger('frequency_days')->default(2);
                }
            });

            if ($this->isColumnNotNull(self::RULES_TABLE, 'name')) {
                Db::statement('ALTER TABLE `' . self::RULES_TABLE . '` MODIFY `name` VARCHAR(255) NULL DEFAULT NULL');
            }
        }

        if (!Schema::hasTable(self::LOGS_TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::LOGS_TABLE, 'scheduled_date')) {
            Schema::table(self::LOGS_TABLE, function (Blueprint $table) {
                $table->date('scheduled_date')->nullable()->after('collection_reminder_rule_id');
            });

            Db::statement('UPDATE `' . self::LOGS_TABLE . '` SET `scheduled_date` = DATE(`sent_at`) WHERE `scheduled_date` IS NULL');
        }

        if (!$this->hasIndex('crm_reminder_log_item_rule_date_unique')) {
            if ($this->hasIndex('crm_reminder_log_item_rule_unique')) {
                Db::statement('ALTER TABLE `' . self::LOGS_TABLE . '` DROP INDEX `crm_reminder_log_item_rule_unique`');
            }

            Db::statement('ALTER TABLE `' . self::LOGS_TABLE . '`
                ADD UNIQUE `crm_reminder_log_item_rule_date_unique` (`collection_item_id`, `collection_reminder_rule_id`, `scheduled_date`)');
        }

        if (!$this->hasForeignKey('collection_reminder_rule_id')) {
            Db::statement('ALTER TABLE `' . self::LOGS_TABLE . '`
                ADD CONSTRAINT `crm_reminder_log_rule_fk`
                FOREIGN KEY (`collection_reminder_rule_id`)
                REFERENCES `' . self::RULES_TABLE . '` (`id`) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::LOGS_TABLE)) {
            Schema::table(self::LOGS_TABLE, function (Blueprint $table) {
                $table->dropUnique('crm_reminder_log_item_rule_date_unique');
                $table->unique(['collection_item_id', 'collection_reminder_rule_id'], 'crm_reminder_log_item_rule_unique');
                $table->dropColumn('scheduled_date');
            });
        }

        if (Schema::hasTable(self::RULES_TABLE)) {
            Schema::table(self::RULES_TABLE, function (Blueprint $table) {
                $table->dropColumn(['start_days_before', 'frequency_days']);
                $table->string('name')->nullable(false)->change();
            });
        }
    }

    protected function isColumnNotNull(string $table, string $column): bool
    {
        return count(Db::select(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND COLUMN_NAME = ? AND IS_NULLABLE = "NO" LIMIT 1',
            [$table, $column]
        )) > 0;
    }

    protected function hasIndex(string $name): bool
    {
        return count(Db::select(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [self::LOGS_TABLE, $name]
        )) > 0;
    }

    protected function hasForeignKey(string $column): bool
    {
        return count(Db::select(
            'SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [self::LOGS_TABLE, $column]
        )) > 0;
    }
};
