<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_design_themes', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->string('name');
            $table->enum('tone', ['corporate', 'playful', 'minimal', 'elegant', 'bold', 'warm']);
            $table->json('niche_affinity')->nullable()->comment('Array de niche_type recomendados. Vacío/null = universal.');
            $table->json('colors')->comment('primary, primary_dark, secondary, accent, neutral_bg, neutral_text');
            $table->string('font_heading');
            $table->string('font_body');
            $table->enum('radius', ['sharp', 'soft', 'round'])->default('soft');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seedThemes();
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_design_themes');
    }

    protected function seedThemes(): void
    {
        $now = date('Y-m-d H:i:s');

        $themes = [
            [
                'handle' => 'corporate-indigo', 'name' => 'Corporativo Índigo', 'tone' => 'corporate',
                'niche_affinity' => [],
                'colors' => ['primary' => '#4f46e5', 'primary_dark' => '#3730a3', 'secondary' => '#0ea5e9', 'accent' => '#f59e0b', 'neutral_bg' => '#f8fafc', 'neutral_text' => '#0f172a'],
                'font_heading' => 'Plus Jakarta Sans', 'font_body' => 'Inter', 'radius' => 'soft',
            ],
            [
                'handle' => 'elegant-emerald', 'name' => 'Elegante Esmeralda', 'tone' => 'elegant',
                'niche_affinity' => ['inmuebles', 'consultorio'],
                'colors' => ['primary' => '#065f46', 'primary_dark' => '#064e3b', 'secondary' => '#10b981', 'accent' => '#d4a373', 'neutral_bg' => '#f7f5f2', 'neutral_text' => '#1c1917'],
                'font_heading' => 'Playfair Display', 'font_body' => 'Lato', 'radius' => 'sharp',
            ],
            [
                'handle' => 'bold-crimson', 'name' => 'Audaz Carmesí', 'tone' => 'bold',
                'niche_affinity' => ['tienda_whatsapp', 'radioemisora'],
                'colors' => ['primary' => '#be123c', 'primary_dark' => '#881337', 'secondary' => '#f97316', 'accent' => '#fbbf24', 'neutral_bg' => '#fff1f2', 'neutral_text' => '#1f2937'],
                'font_heading' => 'Poppins', 'font_body' => 'Inter', 'radius' => 'round',
            ],
            [
                'handle' => 'warm-terracotta', 'name' => 'Cálido Terracota', 'tone' => 'warm',
                'niche_affinity' => ['tienda_whatsapp'],
                'colors' => ['primary' => '#c2410c', 'primary_dark' => '#7c2d12', 'secondary' => '#eab308', 'accent' => '#fde68a', 'neutral_bg' => '#fffbeb', 'neutral_text' => '#292524'],
                'font_heading' => 'Fraunces', 'font_body' => 'Nunito Sans', 'radius' => 'soft',
            ],
            [
                'handle' => 'minimal-slate', 'name' => 'Minimalista Pizarra', 'tone' => 'minimal',
                'niche_affinity' => [],
                'colors' => ['primary' => '#334155', 'primary_dark' => '#0f172a', 'secondary' => '#64748b', 'accent' => '#38bdf8', 'neutral_bg' => '#ffffff', 'neutral_text' => '#1e293b'],
                'font_heading' => 'Inter', 'font_body' => 'Inter', 'radius' => 'sharp',
            ],
            [
                'handle' => 'playful-violet', 'name' => 'Divertido Violeta', 'tone' => 'playful',
                'niche_affinity' => ['radioemisora'],
                'colors' => ['primary' => '#7c3aed', 'primary_dark' => '#5b21b6', 'secondary' => '#ec4899', 'accent' => '#facc15', 'neutral_bg' => '#faf5ff', 'neutral_text' => '#1e1b2e'],
                'font_heading' => 'Baloo 2', 'font_body' => 'Nunito', 'radius' => 'round',
            ],
            [
                'handle' => 'corporate-azure', 'name' => 'Corporativo Azur', 'tone' => 'corporate',
                'niche_affinity' => ['inmuebles'],
                'colors' => ['primary' => '#0369a1', 'primary_dark' => '#0c4a6e', 'secondary' => '#0891b2', 'accent' => '#f97316', 'neutral_bg' => '#f0f9ff', 'neutral_text' => '#0c1a24'],
                'font_heading' => 'Sora', 'font_body' => 'Inter', 'radius' => 'soft',
            ],
            [
                'handle' => 'elegant-charcoal', 'name' => 'Elegante Carbón', 'tone' => 'elegant',
                'niche_affinity' => ['consultorio', 'inmuebles'],
                'colors' => ['primary' => '#1c1917', 'primary_dark' => '#000000', 'secondary' => '#a8a29e', 'accent' => '#d4af37', 'neutral_bg' => '#fafaf9', 'neutral_text' => '#1c1917'],
                'font_heading' => 'Cormorant Garamond', 'font_body' => 'Work Sans', 'radius' => 'sharp',
            ],
            [
                'handle' => 'bold-ocean', 'name' => 'Audaz Océano', 'tone' => 'bold',
                'niche_affinity' => ['radioemisora'],
                'colors' => ['primary' => '#0e7490', 'primary_dark' => '#164e63', 'secondary' => '#06b6d4', 'accent' => '#fb923c', 'neutral_bg' => '#ecfeff', 'neutral_text' => '#0e2a32'],
                'font_heading' => 'Montserrat', 'font_body' => 'Open Sans', 'radius' => 'round',
            ],
            [
                'handle' => 'warm-sand', 'name' => 'Cálido Arena', 'tone' => 'warm',
                'niche_affinity' => ['consultorio'],
                'colors' => ['primary' => '#92400e', 'primary_dark' => '#451a03', 'secondary' => '#d97706', 'accent' => '#fef3c7', 'neutral_bg' => '#fffdf7', 'neutral_text' => '#292524'],
                'font_heading' => 'Libre Baskerville', 'font_body' => 'Source Sans Pro', 'radius' => 'soft',
            ],
            [
                'handle' => 'minimal-forest', 'name' => 'Minimalista Bosque', 'tone' => 'minimal',
                'niche_affinity' => ['generic'],
                'colors' => ['primary' => '#14532d', 'primary_dark' => '#052e16', 'secondary' => '#16a34a', 'accent' => '#a3e635', 'neutral_bg' => '#f0fdf4', 'neutral_text' => '#14231a'],
                'font_heading' => 'Manrope', 'font_body' => 'Manrope', 'radius' => 'sharp',
            ],
            [
                'handle' => 'playful-coral', 'name' => 'Divertido Coral', 'tone' => 'playful',
                'niche_affinity' => ['tienda_whatsapp'],
                'colors' => ['primary' => '#e11d48', 'primary_dark' => '#9f1239', 'secondary' => '#fb7185', 'accent' => '#fde047', 'neutral_bg' => '#fff1f2', 'neutral_text' => '#292524'],
                'font_heading' => 'Quicksand', 'font_body' => 'Mulish', 'radius' => 'round',
            ],
        ];

        foreach ($themes as $theme) {
            \Db::table('aero_sites_design_themes')->insert([
                'handle'         => $theme['handle'],
                'name'           => $theme['name'],
                'tone'           => $theme['tone'],
                'niche_affinity' => json_encode($theme['niche_affinity']),
                'colors'         => json_encode($theme['colors']),
                'font_heading'   => $theme['font_heading'],
                'font_body'      => $theme['font_body'],
                'radius'         => $theme['radius'],
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }
};
