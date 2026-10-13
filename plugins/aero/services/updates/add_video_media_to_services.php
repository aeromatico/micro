<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Video local (Biblioteca de medios) como alternativa a YouTube: interruptor + archivo + póster opcional. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            if (!Schema::hasColumn('aero_services_services', 'video_use_media')) {
                $table->boolean('video_use_media')->default(false)->after('youtube_id');
            }
            if (!Schema::hasColumn('aero_services_services', 'video_media')) {
                $table->string('video_media', 255)->nullable()->after('video_use_media');
            }
            if (!Schema::hasColumn('aero_services_services', 'video_poster')) {
                $table->string('video_poster', 255)->nullable()->after('video_media');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            foreach (['video_poster', 'video_media', 'video_use_media'] as $col) {
                if (Schema::hasColumn('aero_services_services', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
