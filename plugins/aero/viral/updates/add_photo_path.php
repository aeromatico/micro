<?php namespace Aero\Viral\Updates;

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('aero_viral_applications') && !Schema::hasColumn('aero_viral_applications', 'photo_path')) {
            Schema::table('aero_viral_applications', function (Blueprint $t) {
                $t->string('photo_path', 255)->nullable()->after('city'); // foto de perfil, solo si ofrece su perfil a clientes
            });
        }
    }

    public function down()
    {
        // Sin rollback: el esquema solo avanza con october:migrate.
    }
};
