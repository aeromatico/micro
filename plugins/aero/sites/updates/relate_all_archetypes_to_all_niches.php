<?php

use October\Rain\Database\Updates\Migration;

/**
 * v1.23.0 solo universalizó los 2 arquetipos "generic-*" (fix del bug que
 * dejaba sin arquetipos a los nichos nuevos). Pero lo pedido era más amplio:
 * TODOS los arquetipos disponibles para TODOS los nichos, no solo los 2
 * genéricos — un tenant de cualquier rubro puede querer el layout
 * "Portafolio visual" (pensado para inmuebles) o "En vivo y comunidad"
 * (pensado para radioemisora) como punto de partida, aunque el nombre/blocks
 * se hayan diseñado originalmente pensando en otro nicho.
 *
 * niche_type=NULL es "universal, aplica a cualquier nicho" (ver comentario
 * de columna en create_archetypes_table.php) y Archetype::scopeForNiche()
 * ya lo interpreta así — así que alcanza con vaciar la columna en los 8
 * arquetipos restantes que todavía tenían un nicho fijo.
 */
return new class extends Migration
{
    public function up(): void
    {
        \Db::table('aero_sites_archetypes')->update(['niche_type' => null]);
    }

    public function down(): void
    {
        $original = [
            'consultorio-confianza'     => 'consultorio',
            'consultorio-directo'       => 'consultorio',
            'inmuebles-portafolio'      => 'inmuebles',
            'inmuebles-resultados'      => 'inmuebles',
            'radioemisora-vivo'         => 'radioemisora',
            'radioemisora-programacion' => 'radioemisora',
            'tienda-whatsapp-catalogo'  => 'tienda_whatsapp',
            'tienda-whatsapp-oferta'    => 'tienda_whatsapp',
            'generic-estandar'          => 'generic',
            'generic-con-estadisticas'  => 'generic',
        ];

        foreach ($original as $handle => $niche) {
            \Db::table('aero_sites_archetypes')->where('handle', $handle)->update(['niche_type' => $niche]);
        }
    }
};
