<?php namespace Aero\Viral\Updates;

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('aero_viral_applications') && !Schema::hasColumn('aero_viral_applications', 'phone_verified_at')) {
            Schema::table('aero_viral_applications', function (Blueprint $t) {
                $t->timestamp('phone_verified_at')->nullable()->after('phone');
            });
        }
    }

    public function down()
    {
        // Sin rollback: el esquema solo avanza con october:migrate.
    }
};
