<?php namespace Aero\Viral\Updates;

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('aero_viral_applications') && !Schema::hasColumn('aero_viral_applications', 'rate_model')) {
            Schema::table('aero_viral_applications', function (Blueprint $t) {
                $t->string('rate_model', 24)->nullable()->after('level');    // per_second|per_hour|commission_fixed|commission_percent|per_project
                $t->decimal('rate_amount', 10, 2)->nullable()->after('rate_model'); // Bs, o % si commission_percent
            });
        }
    }

    public function down()
    {
        // Sin rollback: el esquema solo avanza con october:migrate.
    }
};
