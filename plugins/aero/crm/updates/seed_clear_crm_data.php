<?php

use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks to avoid cascade issues
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Truncate all CRM tables in reverse dependency order
        $tables = [
            'aero_crm_collection_reminder_logs',
            'aero_crm_collection_reminder_rules',
            'aero_crm_collection_items',
            'aero_crm_contact_list_contact',
            'aero_crm_contact_lists',
            'aero_crm_activities',
            'aero_crm_deals',
            'aero_crm_leads',
            'aero_crm_pipeline_stages',
            'aero_crm_pipelines',
            'aero_crm_team_members',
            'aero_crm_teams',
            'aero_crm_contacts',
            'aero_crm_companies',
            'aero_crm_settings',
        ];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // No-op: clearing data is not reversible
    }
};
