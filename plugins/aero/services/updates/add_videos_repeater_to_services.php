<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Repetidor de videos por servicio (cada uno con versión horizontal 16:9 y/o vertical 9:16, YouTube o Medios).
 * Mueve el video único anterior (youtube_id / video_use_media / video_media / video_poster) al primer ítem
 * y deja esas columnas vacías (no se borran: la migración no es destructiva).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_services_services', 'videos')) {
            Schema::table('aero_services_services', function (Blueprint $table) {
                $table->json('videos')->nullable()->after('icon');
            });
        }

        $hasLegacy = Schema::hasColumn('aero_services_services', 'video_use_media');

        if (!$hasLegacy) {
            return;
        }

        foreach (DB::table('aero_services_services')->get() as $row) {
            $useMedia = (bool) ($row->video_use_media ?? false);
            $item = null;

            if ($useMedia && filled($row->video_media ?? null)) {
                $item = ['title' => 'Cómo funciona', 'use_media' => true, 'media' => $row->video_media, 'poster' => $row->video_poster ?: null];
            } elseif (!$useMedia && filled($row->youtube_id ?? null)) {
                $item = ['title' => 'Cómo funciona', 'use_media' => false, 'youtube' => $row->youtube_id];
            }

            if ($item) {
                DB::table('aero_services_services')->where('id', $row->id)->update([
                    'videos'          => json_encode([$item], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'youtube_id'      => null,
                    'video_use_media' => false,
                    'video_media'     => null,
                    'video_poster'    => null,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_services_services', 'videos')) {
            Schema::table('aero_services_services', function (Blueprint $table) {
                $table->dropColumn('videos');
            });
        }
    }
};
