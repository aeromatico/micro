<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $data = [
            'generic-con-estadisticas' => '
[Nombre del negocio] es [tipo de negocio/rubro] con sede en [ciudad/zona], con [X años] de
trayectoria y [cantidad: proyectos completados/clientes atendidos/etc.] en el mercado.
Ofrecemos [servicios o productos principales] respaldados por resultados medibles. Nos
destacamos por [diferenciador con cifras: tasa de satisfacción, clientes recurrentes,
cobertura, etc.]. Atendemos principalmente a [tipo de cliente objetivo] que valora ver
evidencia concreta antes de decidir.',

            'consultorio-confianza' => '
[Nombre del consultorio/clínica] cuenta con [X años] de experiencia en [especialidad médica]
en [ciudad/zona], y ha atendido a [cantidad] pacientes. Nuestro equipo está formado por
[credenciales: especialistas certificados, formación, etc.]. Ofrecemos [lista de
servicios/especialidades] con un trato cercano y humano. Contamos con pacientes que pueden
dar testimonio de la calidad de atención recibida. Buscamos transmitir confianza y
credibilidad profesional, sin prometer resultados médicos que no podamos garantizar.',

            'consultorio-directo' => '
[Nombre del consultorio/clínica] atiende [especialidad médica] en [ciudad/zona], con turnos
disponibles [rango horario/días] y atención [presencial/urgencias]. Ofrecemos [lista breve
de servicios] para pacientes que necesitan resolver rápido. Nos destacamos por
[diferenciador: disponibilidad inmediata, tecnología, ubicación céntrica, etc.]. Buscamos
transmitir eficiencia y rapidez de atención, sin sonar impersonal.',

            'inmuebles-portafolio' => '
[Nombre de la inmobiliaria] cuenta con un portafolio de [cantidad] propiedades [tipo:
residenciales, comerciales, etc.] en [ciudad/zona]. Ofrecemos [tipo de operación:
compra/venta/alquiler] con opciones variadas para [tipo de cliente objetivo]. Nos
destacamos por [diferenciador: variedad de oferta, fotos/tours virtuales, zonas cubiertas,
etc.]. Buscamos mostrar la amplitud y calidad de nuestras propiedades disponibles,
transmitiendo confianza visual antes que cifras.',

            'inmuebles-resultados' => '
[Nombre de la inmobiliaria] lleva [X años] en el mercado de [ciudad/zona] y ha [cantidad]
propiedades vendidas/alquiladas, con un tiempo promedio de cierre de [X días/semanas].
Ofrecemos [compra/venta/alquiler] respaldados por un historial comprobado. Nos destacamos
por [diferenciador: rapidez de venta, negociación, red de contactos, etc.]. Buscamos
transmitir eficacia y trayectoria, priorizando resultados concretos sobre la estética.',

            'radioemisora-vivo' => '
[Nombre de la radio] transmite en vivo las 24 horas desde [ciudad/zona], con [género
musical/tipo de contenido] y programas destacados como [nombres de programas]. Contamos
con auspiciantes/marcas asociadas como [nombres, si corresponde]. Nos escuchan
principalmente [tipo de oyente]. Buscamos transmitir la energía de estar siempre en vivo y
la cercanía con nuestra comunidad de oyentes.',

            'radioemisora-programacion' => '
[Nombre de la radio] transmite [género musical/tipo de contenido] desde [ciudad/zona], con
una grilla de programación organizada en franjas como [nombres de programas y horarios].
Llevamos [X años] al aire y contamos con [cantidad de oyentes/alcance]. Nos destacamos por
[diferenciador: variedad de programas, conductores reconocidos, etc.]. Buscamos que el
oyente sepa exactamente qué escuchar y a qué hora.',

            'tienda-whatsapp-catalogo' => '
[Nombre de la tienda] vende [tipo de productos] por WhatsApp en [ciudad/zona], con un
catálogo de [cantidad/variedad] productos. Ofrecemos [diferenciador: variedad, precios,
calidad] y atención personalizada para cada pedido. Contamos con clientes satisfechos que
respaldan la confianza en nuestros productos. Buscamos transmitir variedad y seriedad antes
de cerrar la venta por WhatsApp.',

            'tienda-whatsapp-oferta' => '
[Nombre de la tienda] ofrece por tiempo limitado [producto/promoción puntual] con
[descuento/beneficio] para pedidos por WhatsApp en [ciudad/zona]. Ofrecemos [productos
destacados de la oferta] con [diferenciador: envío rápido, stock limitado, etc.]. Buscamos
transmitir urgencia real (sin inventar descuentos falsos) para que el cliente aproveche la
oferta ahora.',
        ];

        foreach ($data as $handle => $prompt) {
            \Db::table('aero_sites_archetypes')
                ->where('handle', $handle)
                ->update([
                    'base_prompt' => trim($prompt),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
        }
    }

    public function down(): void
    {
        // No revierte a NULL a propósito: si algún admin ya editó estos
        // textos a mano después de aplicar esta migración, un rollback no
        // debería pisar ese trabajo.
    }
};
