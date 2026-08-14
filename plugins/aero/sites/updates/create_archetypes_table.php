<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_sites_archetypes', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->string('name');
            $table->string('niche_type')->nullable()->comment('null = universal, aplica a cualquier nicho');
            $table->text('description')->nullable();
            $table->json('blocks')->comment('Secuencia ordenada de bloques Puck: [{"block":"Hero"}, ...]');
            $table->json('recommended_tones')->nullable()->comment('Tonos de DesignTheme afines: corporate|playful|minimal|elegant|bold|warm');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedArchetypes();
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sites_archetypes');
    }

    protected function seedArchetypes(): void
    {
        $now = date('Y-m-d H:i:s');

        $blocks = fn (array $names) => array_map(fn ($b) => ['block' => $b], $names);

        $archetypes = [
            [
                'handle' => 'generic-estandar', 'name' => 'Estándar', 'niche_type' => 'generic',
                'description' => 'Secuencia genérica balanceada para cualquier negocio sin nicho específico.',
                'blocks' => $blocks(['Hero', 'FeatureGrid', 'Testimonials', 'CTASection']),
                'recommended_tones' => ['corporate', 'minimal'],
            ],
            [
                'handle' => 'generic-con-estadisticas', 'name' => 'Con estadísticas', 'niche_type' => 'generic',
                'description' => 'Suma una sección de números/logros antes de las características.',
                'blocks' => $blocks(['Hero', 'Stats', 'FeatureGrid', 'Testimonials', 'CTASection']),
                'recommended_tones' => ['corporate', 'bold'],
            ],
            [
                'handle' => 'consultorio-confianza', 'name' => 'Confianza y credenciales', 'niche_type' => 'consultorio',
                'description' => 'Para clínicas/consultorios: refuerza credibilidad con estadísticas, testimonios y FAQ antes del CTA.',
                'blocks' => $blocks(['Hero', 'FeatureGrid', 'Stats', 'Testimonials', 'FAQ', 'CTASection']),
                'recommended_tones' => ['elegant', 'warm', 'corporate'],
            ],
            [
                'handle' => 'consultorio-directo', 'name' => 'Directo a la cita', 'niche_type' => 'consultorio',
                'description' => 'Versión corta, va directo a la conversión (agendar cita) sin tantas secciones.',
                'blocks' => $blocks(['Hero', 'FeatureGrid', 'Testimonials', 'CTASection']),
                'recommended_tones' => ['warm', 'corporate'],
            ],
            [
                'handle' => 'inmuebles-portafolio', 'name' => 'Portafolio visual', 'niche_type' => 'inmuebles',
                'description' => 'Prioriza galería de propiedades antes de las características.',
                'blocks' => $blocks(['Hero', 'Gallery', 'FeatureGrid', 'Stats', 'Testimonials', 'CTASection']),
                'recommended_tones' => ['elegant', 'corporate'],
            ],
            [
                'handle' => 'inmuebles-resultados', 'name' => 'Enfoque en resultados', 'niche_type' => 'inmuebles',
                'description' => 'Lleva las estadísticas (propiedades vendidas, años de experiencia) justo después del hero.',
                'blocks' => $blocks(['Hero', 'Stats', 'Gallery', 'Testimonials', 'CTASection']),
                'recommended_tones' => ['corporate', 'bold'],
            ],
            [
                'handle' => 'radioemisora-vivo', 'name' => 'En vivo y comunidad', 'niche_type' => 'radioemisora',
                'description' => 'Destaca el video/stream en vivo y los partners/auspiciantes.',
                'blocks' => $blocks(['Hero', 'Video', 'FeatureGrid', 'Testimonials', 'LogoCloud', 'CTASection']),
                'recommended_tones' => ['playful', 'bold'],
            ],
            [
                'handle' => 'radioemisora-programacion', 'name' => 'Programación destacada', 'niche_type' => 'radioemisora',
                'description' => 'Prioriza la grilla de programación (características) antes del video.',
                'blocks' => $blocks(['Hero', 'FeatureGrid', 'Video', 'Stats', 'CTASection']),
                'recommended_tones' => ['bold', 'playful'],
            ],
            [
                'handle' => 'tienda-whatsapp-catalogo', 'name' => 'Catálogo y confianza', 'niche_type' => 'tienda_whatsapp',
                'description' => 'Galería de productos + valoraciones antes de cerrar con testimonios.',
                'blocks' => $blocks(['Hero', 'Gallery', 'FeatureGrid', 'Rating', 'Testimonials', 'CTASection']),
                'recommended_tones' => ['warm', 'bold', 'playful'],
            ],
            [
                'handle' => 'tienda-whatsapp-oferta', 'name' => 'Oferta directa', 'niche_type' => 'tienda_whatsapp',
                'description' => 'Versión corta con un banner de promoción destacado, para campañas puntuales.',
                'blocks' => $blocks(['Hero', 'Banner', 'Gallery', 'Rating', 'CTASection']),
                'recommended_tones' => ['warm', 'playful'],
            ],
        ];

        foreach ($archetypes as $i => $a) {
            \Db::table('aero_sites_archetypes')->insert([
                'handle'             => $a['handle'],
                'name'               => $a['name'],
                'niche_type'         => $a['niche_type'],
                'description'        => $a['description'],
                'blocks'             => json_encode($a['blocks']),
                'recommended_tones'  => json_encode($a['recommended_tones']),
                'is_active'          => true,
                'sort_order'         => $i + 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
    }
};
