<?php namespace Aero\Viral\Updates;

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('aero_viral_applications') && !Schema::hasColumn('aero_viral_applications', 'positive_feedbacks')) {
            Schema::table('aero_viral_applications', function (Blueprint $t) {
                $t->unsignedInteger('positive_feedbacks')->default(0)->after('rate_amount'); // del nivel actual
                $t->timestamp('level_changed_at')->nullable()->after('positive_feedbacks');
            });
        }
    }

    public function down()
    {
        // Sin rollback: el esquema solo avanza con october:migrate.
    }
};
