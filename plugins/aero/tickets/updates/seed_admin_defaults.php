<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_tickets_locations')) {
            $map = [
                'La Paz' => ['La Paz', 'El Alto', 'Viacha', 'Copacabana', 'Coroico'],
                'Cochabamba' => ['Cochabamba', 'Quillacollo', 'Sacaba', 'Colcapirhua', 'Tiquipaya'],
                'Santa Cruz' => ['Santa Cruz de la Sierra', 'Montero', 'Warnes', 'Cotoca', 'La Guardia'],
                'Oruro' => ['Oruro', 'Huanuni', 'Challapata'],
                'Potosí' => ['Potosí', 'Uyuni', 'Villazón', 'Tupiza'],
                'Chuquisaca' => ['Sucre', 'Monteagudo', 'Camargo'],
                'Tarija' => ['Tarija', 'Yacuiba', 'Villamontes', 'Bermejo'],
                'Beni' => ['Trinidad', 'Riberalta', 'Guayaramerín', 'Rurrenabaque'],
                'Pando' => ['Cobija', 'Porvenir'],
            ];
            $i = 0;
            foreach ($map as $dept => $cities) {
                $d = \Aero\Tickets\Models\Location::firstOrCreate(['type' => 'department', 'name' => $dept, 'parent_id' => null], ['sort_order' => ++$i * 10, 'is_active' => true]);
                foreach ($cities as $j => $c) {
                    \Aero\Tickets\Models\Location::firstOrCreate(['type' => 'city', 'name' => $c, 'parent_id' => $d->id], ['sort_order' => ($j + 1) * 10, 'is_active' => true]);
                }
            }
        }

        if (Schema::hasTable('aero_tickets_templates')) {
            foreach (\Aero\Tickets\Classes\TemplateDefaults::all() as $code => $t) {
                \Aero\Tickets\Models\Template::firstOrCreate(['code' => $code], ['name' => $t['name'], 'subject' => $t['subject'], 'body' => $t['body'], 'is_active' => true]);
            }
        }

        // Roles globales de la plataforma para su personal (se asignan en Usuarios).
        if (class_exists(\Backend\Models\UserRole::class) && Schema::hasTable('backend_user_roles')) {
            $roles = [
                'tickets_moderator' => ['Entradas · Moderación', 'Aprueba organizadores y modera eventos.', ['aero.tickets.admin.view', 'aero.tickets.admin.organizers', 'aero.tickets.admin.events']],
                'tickets_finance' => ['Entradas · Finanzas', 'Verifica pagos y gestiona comisiones y obligaciones.', ['aero.tickets.admin.view', 'aero.tickets.admin.finance', 'aero.tickets.payments']],
                'tickets_support' => ['Entradas · Soporte', 'Consulta el panel global y la auditoría (solo lectura).', ['aero.tickets.admin.view']],
            ];
            foreach ($roles as $code => [$name, $desc, $perms]) {
                if (!\Backend\Models\UserRole::where('code', $code)->exists()) {
                    $r = new \Backend\Models\UserRole();
                    $r->name = $name;
                    $r->code = $code;
                    $r->description = $desc;
                    $r->permissions = array_fill_keys($perms, 1);
                    $r->save();
                }
            }
        }
    }

    public function down(): void
    {
    }
};
