<?php

use Illuminate\Support\Facades\DB;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_activities', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('subject');
            $table->index(['tenant_id', 'status', 'due_at'], 'aero_crm_activities_status_index');
        });

        DB::table('aero_crm_activities')
            ->whereNotNull('completed_at')
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('aero_crm_activities', function (Blueprint $table) {
            $table->dropIndex('aero_crm_activities_status_index');
            $table->dropColumn('status');
        });
    }
};
