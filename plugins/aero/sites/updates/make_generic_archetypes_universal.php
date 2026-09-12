<?php

use October\Rain\Database\Updates\Migration;

/**
 * Los 2 arquetipos "generic-*" se sembraron con niche_type='generic' (el
 * nicho por defecto), no NULL — pero la columna documenta NULL como
 * "universal, aplica a cualquier nicho" y Archetype::scopeForNiche() ya
 * incluye whereNull('niche_type') como fallback. Como resultado, cualquier
 * nicho sin arquetipos propios (los 14 nuevos agregados en esta sesión, más
 * tienda_online) se quedaba sin ningún arquetipo — el selector desaparecía
 * del todo en el panel de generación con IA.
 *
 * Fix: pasar esos 2 arquetipos a niche_type=NULL para que actúen como el
 * fallback universal que la columna siempre dijo que eran. Cualquier nicho
 * nuevo futuro sin arquetipos dedicados los hereda automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        \Db::table('aero_sites_archetypes')
            ->whereIn('handle', ['generic-estandar', 'generic-con-estadisticas'])
            ->update(['niche_type' => null]);
    }

    public function down(): void
    {
        \Db::table('aero_sites_archetypes')
            ->whereIn('handle', ['generic-estandar', 'generic-con-estadisticas'])
            ->update(['niche_type' => 'generic']);
    }
};
