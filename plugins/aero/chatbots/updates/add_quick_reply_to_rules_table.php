<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Una regla puede apuntar a una respuesta rápida del CRM en lugar de llevar
 * su propio texto: así la respuesta se escribe una sola vez.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('aero_chatbots_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('quick_reply_id')->nullable()->after('trigger_keywords')->index();
            $table->text('response_text')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('aero_chatbots_rules', function (Blueprint $table) {
            $table->dropIndex(['quick_reply_id']);
            $table->dropColumn('quick_reply_id');
        });
    }
};
