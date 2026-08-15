<?php

use Aero\Sites\Classes\ColorPalette;
use October\Rain\Database\Updates\Migration;

/**
 * Convierte `colors` de plano (una sola paleta pensada para superficies
 * claras) a la forma dual { light, dark } que necesita el toggle de modo
 * claro/oscuro. La variante dark se deriva programáticamente (ver
 * ColorPalette::deriveDark) en vez de curarse a mano registro por registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        $themes = \Db::table('aero_sites_design_themes')->get();

        foreach ($themes as $theme) {
            $colors = json_decode($theme->colors, true) ?: [];

            if (isset($colors['light'], $colors['dark'])) {
                continue;
            }

            $dual = [
                'light' => ColorPalette::deriveLightSurface($colors),
                'dark'  => ColorPalette::deriveDark($colors),
            ];

            \Db::table('aero_sites_design_themes')
                ->where('id', $theme->id)
                ->update(['colors' => json_encode($dual)]);
        }
    }

    public function down(): void
    {
        $themes = \Db::table('aero_sites_design_themes')->get();

        foreach ($themes as $theme) {
            $colors = json_decode($theme->colors, true) ?: [];

            if (!isset($colors['light'])) {
                continue;
            }

            $light = $colors['light'];

            \Db::table('aero_sites_design_themes')
                ->where('id', $theme->id)
                ->update(['colors' => json_encode([
                    'primary'      => $light['primary'] ?? '#4f46e5',
                    'primary_dark' => $light['primary_dark'] ?? '#3730a3',
                    'secondary'    => $light['secondary'] ?? '#0ea5e9',
                    'accent'       => $light['accent'] ?? '#f59e0b',
                    'neutral_bg'   => $light['surface_bg'] ?? '#f8fafc',
                    'neutral_text' => $light['surface_text'] ?? '#0f172a',
                ])]);
        }
    }
};
