<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_archetypes', function (Blueprint $table) {
            $table->text('tone_instructions')->nullable()->after('description');
            $table->text('target_audience')->nullable()->after('tone_instructions');
        });

        $this->updateSeededGuidance();
    }

    public function down(): void
    {
        Schema::table('aero_sites_archetypes', function (Blueprint $table) {
            $table->dropColumn(['tone_instructions', 'target_audience']);
        });
    }

    protected function updateSeededGuidance(): void
    {
        $blocks = fn (array $pairs) => array_map(
            fn ($p) => ['block' => $p[0], 'instruction' => $p[1] ?? ''],
            $pairs
        );

        $data = [
            'generic-estandar' => [
                'tone' => 'Profesional pero cercano. Frases cortas y directas. Evitar jerga técnica innecesaria y adjetivos vacíos ("líder", "el mejor") sin respaldo.',
                'audience' => 'Clientes comparando proveedores, sensibles a que la propuesta de valor sea clara y concreta.',
                'blocks' => [
                    ['Hero', 'Título: una frase con la propuesta de valor principal. Subtítulo: qué hace el negocio en 1-2 líneas concretas, sin relleno.'],
                    ['FeatureGrid', '3 características que respondan "¿por qué elegirnos?", cada una con el beneficio para el cliente, no solo la característica técnica.'],
                    ['Testimonials', '2 testimonios breves y creíbles, con nombre y rol/empresa realistas acordes al negocio descrito.'],
                    ['CTASection', 'Llamado a la acción claro y de bajo compromiso (ej. "contactanos", "pedí tu cotización"), no agresivo.'],
                ],
            ],
            'generic-con-estadisticas' => [
                'tone' => 'Profesional, orientado a resultados. Usar cifras y datos concretos donde sea posible.',
                'audience' => 'Clientes que valoran evidencia y trayectoria antes de decidir.',
                'blocks' => [
                    ['Hero', 'Título con la propuesta de valor; puede insinuar trayectoria sin repetir las cifras que van en Stats.'],
                    ['Stats', '3 cifras creíbles (no exageradas) coherentes con el negocio descrito: años de experiencia, clientes atendidos, proyectos completados.'],
                    ['FeatureGrid', '3 características con beneficio concreto para el cliente.'],
                    ['Testimonials', '2 testimonios breves y creíbles.'],
                    ['CTASection', 'CTA claro y de bajo compromiso.'],
                ],
            ],
            'consultorio-confianza' => [
                'tone' => 'Cálido y profesional a la vez. Transmitir confianza y cuidado humano, sin sonar frío ni corporativo. Evitar promesas médicas exageradas o garantías de resultado.',
                'audience' => 'Pacientes potenciales evaluando dónde atenderse, preocupados por la calidad de atención, el trato y la confianza en el profesional.',
                'blocks' => [
                    ['Hero', 'Título: especialidad médica + promesa de cuidado (ej. "Atención [especialidad] con calidez humana"). Subtítulo: años de experiencia o especialización, sin sonar a folleto genérico.'],
                    ['FeatureGrid', '3-4 servicios/especialidades concretas mencionadas en la descripción del negocio, cada uno con el beneficio para el paciente, no solo el nombre del servicio.'],
                    ['Stats', 'Cifras de credibilidad médica (años de experiencia, pacientes atendidos) SOLO si son coherentes con lo descrito — no inventar cifras si no hay base.'],
                    ['Testimonials', 'Testimonios centrados en el TRATO y la confianza, no solo en el resultado médico (ej. "me explicó todo con paciencia").'],
                    ['FAQ', 'Preguntas frecuentes reales de pacientes: horarios, seguros/obras sociales, primera consulta, urgencias. Respuestas breves y claras.'],
                    ['CTASection', 'CTA centrado en agendar consulta (ej. "Agenda tu consulta hoy"), tono tranquilizador.'],
                ],
            ],
            'consultorio-directo' => [
                'tone' => 'Directo y eficiente, para pacientes que ya saben lo que buscan y quieren agendar rápido.',
                'audience' => 'Pacientes que llegan con una necesidad clara (derivados, urgencia) y priorizan rapidez de atención.',
                'blocks' => [
                    ['Hero', 'Título directo con la especialidad. Subtítulo breve sobre disponibilidad/rapidez de atención.'],
                    ['FeatureGrid', '3 razones concretas para elegir la atención (disponibilidad, tecnología, especialización), priorizando lo accionable.'],
                    ['Testimonials', '1-2 testimonios cortos enfocados en la rapidez/eficiencia de la atención.'],
                    ['CTASection', 'CTA urgente pero no alarmista: "Reservá tu turno ahora".'],
                ],
            ],
            'inmuebles-portafolio' => [
                'tone' => 'Aspiracional pero honesto. Profesionalismo inmobiliario sin sonar a venta agresiva.',
                'audience' => 'Personas buscando comprar, vender o alquilar, comparando inmobiliarias por confianza y variedad de oferta.',
                'blocks' => [
                    ['Hero', 'Título con la propuesta de valor (ej. encontrar el próximo hogar). Subtítulo mencionando la zona/tipo de propiedades si se conoce.'],
                    ['Gallery', 'Alt text/keywords de imagen coherentes con tipo de propiedad (ej. "moderna sala de estar", "fachada casa residencial").'],
                    ['FeatureGrid', 'Beneficios del SERVICIO inmobiliario (asesoría, financiamiento, gestión), no características de una propiedad puntual.'],
                    ['Stats', 'Cifras de trayectoria: propiedades vendidas/alquiladas, años en el mercado, zonas cubiertas.'],
                    ['Testimonials', 'Testimonios de compradores/vendedores mencionando confianza y acompañamiento en el proceso.'],
                    ['CTASection', 'CTA para agendar una visita o consulta con un asesor.'],
                ],
            ],
            'inmuebles-resultados' => [
                'tone' => 'Orientado a resultados y números, transmite eficacia del equipo.',
                'audience' => 'Personas que ya conocen el mercado y buscan la inmobiliaria con mejor track record.',
                'blocks' => [
                    ['Hero', 'Título enfocado en resultados/trayectoria más que en aspiración.'],
                    ['Stats', 'Cifras destacadas primero: ventas cerradas, tiempo promedio de venta, satisfacción de clientes.'],
                    ['Gallery', 'Alt/keywords coherentes con tipo de propiedad.'],
                    ['Testimonials', 'Testimonios con resultados concretos (ej. "vendieron mi casa en tiempo récord").'],
                    ['CTASection', 'CTA orientado a pedir una tasación o evaluación de propiedad.'],
                ],
            ],
            'radioemisora-vivo' => [
                'tone' => 'Enérgico, cercano, con personalidad de marca radial (puede ser informal según el estilo de la radio).',
                'audience' => 'Oyentes actuales y potenciales, además de posibles anunciantes/patrocinadores.',
                'blocks' => [
                    ['Hero', 'Título con el nombre/propuesta de la radio (género musical, foco de contenido). Subtítulo invitando a escuchar en vivo.'],
                    ['Video', 'Caption invitando a sintonizar en vivo o ver el último programa destacado.'],
                    ['FeatureGrid', 'Programas/franjas horarias destacadas o razones para escuchar esta radio en particular.'],
                    ['Testimonials', 'Comentarios de oyentes sobre la programación o el ambiente de la radio.'],
                    ['LogoCloud', 'Auspiciantes/marcas asociadas — omitir el bloque si no se mencionan marcas reales en la descripción del negocio.'],
                    ['CTASection', 'CTA para escuchar en vivo o seguir en redes sociales.'],
                ],
            ],
            'radioemisora-programacion' => [
                'tone' => 'Informativo y organizado, como una grilla de programación.',
                'audience' => 'Oyentes buscando saber qué programas hay y a qué hora.',
                'blocks' => [
                    ['Hero', 'Título breve sobre la radio, subtítulo apuntando a "conocé nuestra programación".'],
                    ['FeatureGrid', 'Programas principales con horario/franja y una línea de descripción cada uno.'],
                    ['Video', 'Contenido destacado o resumen de un programa/segmento.'],
                    ['Stats', 'Alcance de la radio: oyentes, años al aire, cobertura.'],
                    ['CTASection', 'CTA para escuchar en vivo.'],
                ],
            ],
            'tienda-whatsapp-catalogo' => [
                'tone' => 'Cercano y práctico, como hablarle a un cliente por WhatsApp. Emojis con moderación solo si el negocio lo amerita.',
                'audience' => 'Compradores que buscan rapidez y confianza para comprar por WhatsApp, sensibles al precio y la atención personalizada.',
                'blocks' => [
                    ['Hero', 'Título con la propuesta de venta y mención de compra fácil por WhatsApp. Subtítulo con un gancho (envíos, variedad, precios).'],
                    ['Gallery', 'Alt/keywords de imagen coherentes con los productos descritos por el negocio.'],
                    ['FeatureGrid', 'Ventajas de comprar ahí: variedad, precio, rapidez de entrega, atención personalizada.'],
                    ['Rating', 'Valoración alta (4-5) coherente con un negocio que se presenta con confianza.'],
                    ['Testimonials', 'Testimonios breves de compradores satisfechos, tono coloquial.'],
                    ['CTASection', 'CTA directo a WhatsApp: "Pedí por WhatsApp ahora".'],
                ],
            ],
            'tienda-whatsapp-oferta' => [
                'tone' => 'Urgente y persuasivo, estilo campaña/promoción puntual.',
                'audience' => 'Compradores impulsivos u oportunistas, atraídos por ofertas y tiempo limitado.',
                'blocks' => [
                    ['Hero', 'Título con gancho de oferta/promoción. Subtítulo con el beneficio principal de comprar ahora.'],
                    ['Banner', 'Mensaje de urgencia/promoción destacado — solo si es coherente con lo descrito, sin inventar porcentajes de descuento falsos.'],
                    ['Gallery', 'Fotos de los productos en oferta.'],
                    ['Rating', 'Valoración alta para reforzar confianza antes del cierre.'],
                    ['CTASection', 'CTA urgente a WhatsApp para aprovechar la oferta.'],
                ],
            ],
        ];

        foreach ($data as $handle => $spec) {
            \Db::table('aero_sites_archetypes')
                ->where('handle', $handle)
                ->update([
                    'tone_instructions' => $spec['tone'],
                    'target_audience'   => $spec['audience'],
                    'blocks'            => json_encode($blocks($spec['blocks'])),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
        }
    }
};
