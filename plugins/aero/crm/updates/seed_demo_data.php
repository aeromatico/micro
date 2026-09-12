<?php

use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = DB::table('aero_sites_tenants')->orderBy('id')->value('id');
        if (!$tenantId) {
            return;
        }

        // Create a backend user if none exist
        $ownerId = DB::table('backend_users')->orderBy('id')->value('id');
        if (!$ownerId) {
            $ownerId = DB::table('backend_users')->insertGetId([
                'first_name'  => 'Admin',
                'last_name'   => 'CRM',
                'login'       => 'admin_crm',
                'email'       => 'admin@crm.test',
                'password'    => bcrypt('admin123'),
                'is_activated'=> true,
                'is_superuser'=> true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // ── 1. Pipeline & Stages ────────────────────────────────────────
        $pipelineId = DB::table('aero_crm_pipelines')->insertGetId([
            'tenant_id' => $tenantId,
            'name'      => 'Ventas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stageIds = [];
        $stages = [
            ['name' => 'Nuevo',      'color' => '#64748b', 'sort_order' => 0],
            ['name' => 'Contactado', 'color' => '#3b82f6', 'sort_order' => 1],
            ['name' => 'Propuesta',  'color' => '#f59e0b', 'sort_order' => 2],
            ['name' => 'Ganado',     'color' => '#22c55e', 'sort_order' => 3, 'is_won' => true],
            ['name' => 'Perdido',    'color' => '#ef4444', 'sort_order' => 4, 'is_lost' => true],
        ];
        foreach ($stages as $stage) {
            $stageIds[] = DB::table('aero_crm_pipeline_stages')->insertGetId([
                'tenant_id'   => $tenantId,
                'pipeline_id' => $pipelineId,
                'name'        => $stage['name'],
                'color'       => $stage['color'],
                'sort_order'  => $stage['sort_order'],
                'is_won'      => $stage['is_won'] ?? false,
                'is_lost'     => $stage['is_lost'] ?? false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // ── 2. Companies ────────────────────────────────────────────────
        $companies = [
            ['name' => 'TechBol Solutions',    'website' => 'https://techbol.com.bo',     'industry' => 'Tecnología',       'phone' => '+591 2 2345678', 'address' => 'Av. Arce 2456, La Paz'],
            ['name' => 'Minera SurReal',        'website' => 'https://surreal.com.bo',      'industry' => 'Minería',          'phone' => '+591 2 2789012', 'address' => 'Calle Comercio 890, Oruro'],
            ['name' => 'AgroPampa SAC',         'website' => 'https://agropampa.com.bo',    'industry' => 'Agricultura',      'phone' => '+591 3 3456789', 'address' => 'Av. Ballivián 1234, Santa Cruz'],
            ['name' => 'Constructora Horizonte','website' => 'https://horizonte.com.bo',    'industry' => 'Construcción',     'phone' => '+591 2 2890123', 'address' => 'Calle Colón 567, La Paz'],
            ['name' => 'Logística Express',     'website' => 'https://logexpress.com.bo',   'industry' => 'Logística',        'phone' => '+591 3 3567890', 'address' => 'Av. Primero de Mayo 2345, Santa Cruz'],
            ['name' => 'Clínica MedSur',        'website' => 'https://medsur.com.bo',       'industry' => 'Salud',            'phone' => '+591 2 2345679', 'address' => 'Av. 6 de Agosto 789, La Paz'],
            ['name' => ' EduSoft Bolivia',      'website' => 'https://edusoft.com.bo',      'industry' => 'Educación',        'phone' => '+591 4 4234567', 'address' => 'Calle Baptista 345, Cochabamba'],
            ['name' => 'Transporte Andino',     'website' => 'https://tandino.com.bo',      'industry' => 'Transporte',       'phone' => '+591 3 3678901', 'address' => 'Av. Santa Cruz 456, Santa Cruz'],
        ];

        $companyIds = [];
        foreach ($companies as $c) {
            $companyIds[] = DB::table('aero_crm_companies')->insertGetId([
                'tenant_id'  => $tenantId,
                'name'       => trim($c['name']),
                'website'    => $c['website'],
                'industry'   => $c['industry'],
                'phone'      => $c['phone'],
                'address'    => $c['address'],
                'owner_id'   => $ownerId,
                'social_links' => json_encode(['linkedin' => '']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── 3. Contacts ─────────────────────────────────────────────────
        $contacts = [
            ['first_name' => 'Carlos',   'last_name' => 'Mamani Quispe',    'email' => 'carlos.mamani@techbol.com.bo',      'phone' => '+591 71234567', 'company_idx' => 0, 'source' => 'website'],
            ['first_name' => 'María',    'last_name' => 'García López',     'email' => 'maria.garcia@surreal.com.bo',        'phone' => '+591 72345678', 'company_idx' => 1, 'source' => 'referral'],
            ['first_name' => 'Juan',     'last_name' => 'Fernández Vargas', 'email' => 'juan.fernandez@agropampa.com.bo',    'phone' => '+591 73456789', 'company_idx' => 2, 'source' => 'social_media'],
            ['first_name' => 'Ana',      'last_name' => 'Torres Ríos',      'email' => 'ana.torres@horizonte.com.bo',        'phone' => '+591 74567890', 'company_idx' => 3, 'source' => 'cold_call'],
            ['first_name' => 'Luis',     'last_name' => 'Herrera Castillo', 'email' => 'luis.herrera@logexpress.com.bo',     'phone' => '+591 75678901', 'company_idx' => 4, 'source' => 'event'],
            ['first_name' => 'Patricia', 'last_name' => 'Mendoza Gutiérrez','email' => 'patricia.mendoza@medsur.com.bo',     'phone' => '+591 76789012', 'company_idx' => 5, 'source' => 'website'],
            ['first_name' => 'Roberto',  'last_name' => 'Choque Apaza',     'email' => 'roberto.choque@edusoft.com.bo',      'phone' => '+591 77890123', 'company_idx' => 6, 'source' => 'referral'],
            ['first_name' => 'Claudia',  'last_name' => 'Ramos Díaz',       'email' => 'claudia.ramos@tandino.com.bo',       'phone' => '+591 78901234', 'company_idx' => 7, 'source' => 'website'],
            ['first_name' => 'Fernando', 'last_name' => 'Vargas Morales',   'email' => 'fernando.vargas@gmail.com',          'phone' => '+591 79012345', 'company_idx' => null, 'source' => 'social_media'],
            ['first_name' => 'Gabriela', 'last_name' => 'López Fernández',  'email' => 'gabriela.lopez@outlook.com',         'phone' => '+591 70123456', 'company_idx' => null, 'source' => 'website'],
            ['first_name' => 'Diego',    'last_name' => 'Sánchez Pereira',  'email' => 'diego.sanchez@techbol.com.bo',       'phone' => '+591 71234568', 'company_idx' => 0, 'source' => 'referral'],
            ['first_name' => 'Valeria',  'last_name' => 'Jiménez Soliz',    'email' => 'valeria.jimenez@surreal.com.bo',     'phone' => '+591 72345679', 'company_idx' => 1, 'source' => 'cold_call'],
            ['first_name' => 'Andrés',   'last_name' => 'Cáceres Luna',     'email' => 'andres.caceres@hotmail.com',         'phone' => '+591 73456790', 'company_idx' => null, 'source' => 'event'],
            ['first_name' => 'Sofía',    'last_name' => 'Pérez Gómez',      'email' => 'sofia.perez@medsur.com.bo',          'phone' => '+591 74567891', 'company_idx' => 5, 'source' => 'website'],
            ['first_name' => 'Miguel',   'last_name' => 'Rojas Tintaya',    'email' => 'miguel.rojas@horizonte.com.bo',      'phone' => '+591 75678902', 'company_idx' => 3, 'source' => 'social_media'],
        ];

        $contactIds = [];
        foreach ($contacts as $c) {
            $contactIds[] = DB::table('aero_crm_contacts')->insertGetId([
                'tenant_id'  => $tenantId,
                'company_id' => $c['company_idx'] !== null ? $companyIds[$c['company_idx']] : null,
                'first_name' => $c['first_name'],
                'last_name'  => $c['last_name'],
                'email'      => $c['email'],
                'phone'      => $c['phone'],
                'source'     => $c['source'],
                'owner_id'   => $ownerId,
                'social_links' => json_encode(['linkedin' => '', 'facebook' => '', 'instagram' => '']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── 4. Deals ────────────────────────────────────────────────────
        $deals = [
            ['title' => 'Licencia Software ERP',       'value' => 15000.00, 'contact_idx' => 0,  'company_idx' => 0, 'stage_idx' => 2, 'status' => 'open', 'days_offset' => -15],
            ['title' => 'Consultoría Minera',           'value' => 45000.00, 'contact_idx' => 1,  'company_idx' => 1, 'stage_idx' => 1, 'status' => 'open', 'days_offset' => -10],
            ['title' => 'Sistema de Riego Inteligente',  'value' => 28000.00, 'contact_idx' => 2,  'company_idx' => 2, 'stage_idx' => 3, 'status' => 'won',  'days_offset' => -30],
            ['title' => 'Obra Edificio Residencial',     'value' => 120000.00,'contact_idx' => 3,  'company_idx' => 3, 'stage_idx' => 0, 'status' => 'open', 'days_offset' => -5],
            ['title' => 'Flota de Transporte',           'value' => 85000.00, 'contact_idx' => 4,  'company_idx' => 4, 'stage_idx' => 4, 'status' => 'lost', 'days_offset' => -45],
            ['title' => 'Equipamiento Médico',           'value' => 67000.00, 'contact_idx' => 5,  'company_idx' => 5, 'stage_idx' => 2, 'status' => 'open', 'days_offset' => -12],
            ['title' => 'Plataforma E-Learning',         'value' => 32000.00, 'contact_idx' => 6,  'company_idx' => 6, 'stage_idx' => 1, 'status' => 'open', 'days_offset' => -8],
            ['title' => 'GPS Flotillas',                 'value' => 18000.00, 'contact_idx' => 7,  'company_idx' => 7, 'stage_idx' => 3, 'status' => 'won',  'days_offset' => -25],
            ['title' => 'Desarrollo App Móvil',          'value' => 22000.00, 'contact_idx' => 8,  'company_idx' => null, 'stage_idx' => 0, 'status' => 'open', 'days_offset' => -3],
            ['title' => 'Marketing Digital Q4',          'value' => 9500.00,  'contact_idx' => 9,  'company_idx' => null, 'stage_idx' => 1, 'status' => 'open', 'days_offset' => -7],
            ['title' => 'Soporte TI Anual',              'value' => 12000.00, 'contact_idx' => 10, 'company_idx' => 0, 'stage_idx' => 2, 'status' => 'open', 'days_offset' => -20],
            ['title' => 'Auditoría Ambiental',           'value' => 35000.00, 'contact_idx' => 11, 'company_idx' => 1, 'stage_idx' => 3, 'status' => 'won',  'days_offset' => -40],
            ['title' => 'Capacitación Empresarial',      'value' => 6000.00,  'contact_idx' => 12, 'company_idx' => null, 'stage_idx' => 0, 'status' => 'open', 'days_offset' => -2],
            ['title' => 'Monitor Paciente',              'value' => 54000.00, 'contact_idx' => 13, 'company_idx' => 5, 'stage_idx' => 1, 'status' => 'open', 'days_offset' => -6],
            ['title' => 'Reformas Oficinas',             'value' => 27000.00, 'contact_idx' => 14, 'company_idx' => 3, 'stage_idx' => 4, 'status' => 'lost', 'days_offset' => -35],
        ];

        $dealIds = [];
        foreach ($deals as $d) {
            $closedAt = null;
            if (in_array($d['status'], ['won', 'lost'])) {
                $closedAt = Carbon::now()->addDays($d['days_offset'])->toDateTimeString();
            }

            $dealIds[] = DB::table('aero_crm_deals')->insertGetId([
                'tenant_id'   => $tenantId,
                'pipeline_id' => $pipelineId,
                'stage_id'    => $stageIds[$d['stage_idx']],
                'contact_id'  => $contactIds[$d['contact_idx']],
                'company_id'  => $d['company_idx'] !== null ? $companyIds[$d['company_idx']] : null,
                'title'       => $d['title'],
                'value'       => $d['value'],
                'currency'    => 'BOB',
                'owner_id'    => $ownerId,
                'expected_close_date' => Carbon::now()->addDays($d['days_offset'] + 30)->toDateString(),
                'status'      => $d['status'],
                'in_pipeline' => $d['status'] === 'open',
                'closed_at'   => $closedAt,
                'sort_order'  => 0,
                'created_at'  => Carbon::now()->addDays($d['days_offset']),
                'updated_at'  => now(),
            ]);
        }

        // ── 5. Leads ────────────────────────────────────────────────────
        $leads = [
            ['name' => 'Roberto Carlos Álvarez',  'email' => 'rc.alvarez@hotmail.com',     'phone' => '+591 71111111', 'company_name' => 'Constructora Andina',  'source' => 'website',    'status' => 'new'],
            ['name' => 'Lucía Fernanda Copa',      'email' => 'lucia.copa@gmail.com',       'phone' => '+591 72222222', 'company_name' => 'Distribuidora COPA',   'source' => 'social_media','status' => 'contacted'],
            ['name' => 'Eduardo Quispe Machaca',   'email' => 'eduardo.quispe@outlook.com', 'phone' => '+591 73333333', 'company_name' => 'Minera Machaca',       'source' => 'referral',   'status' => 'qualified'],
            ['name' => 'Mónica Estrada Villca',    'email' => 'monica.estrada@yahoo.com',   'phone' => '+591 74444444', 'company_name' => 'Villca & Asociados',   'source' => 'event',      'status' => 'new'],
            ['name' => 'Álvaro Mamani Condori',    'email' => 'alvaro.mamani@techbol.com.bo','phone'=> '+591 75555555', 'company_name' => null,                   'source' => 'cold_call',  'status' => 'disqualified'],
            ['name' => 'Sandra Pinto Rojas',       'email' => 'sandra.pinto@logexpress.com.bo','phone'=> '+591 76666666','company_name' => 'Logística Express',    'source' => 'website',    'status' => 'contacted'],
            ['name' => 'Víctor Hugo Torres',       'email' => 'victor.torres@gmail.com',    'phone' => '+591 77777777', 'company_name' => 'Torres Constructora',  'source' => 'social_media','status' => 'new'],
            ['name' => 'Paola Andrea Zambrana',    'email' => 'paola.zambrana@hotmail.com', 'phone' => '+591 78888888', 'company_name' => 'Zambrana Group',       'source' => 'referral',   'status' => 'qualified'],
        ];

        foreach ($leads as $l) {
            DB::table('aero_crm_leads')->insertGetId([
                'tenant_id'   => $tenantId,
                'name'        => $l['name'],
                'email'       => $l['email'],
                'phone'       => $l['phone'],
                'company_name'=> $l['company_name'],
                'source'      => $l['source'],
                'status'      => $l['status'],
                'owner_id'    => $ownerId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // ── 6. Activities ───────────────────────────────────────────────
        $activities = [
            // Deals activities
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 0,  'type' => 'call',    'subject' => 'Llamada inicial al cliente',           'description' => 'Se contactó al cliente para presentar la propuesta de licencia ERP.', 'days_offset' => -14, 'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 0,  'type' => 'email',   'subject' => 'Envío de cotización',                  'description' => 'Se envió la cotización detallada por correo electrónico.', 'days_offset' => -10, 'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 0,  'type' => 'meeting','subject' => 'Reunión de seguimiento',               'description' => 'Reunión programada para revisar los requisitos del ERP.', 'days_offset' => -2,  'completed' => false],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 1,  'type' => 'whatsapp','subject' => 'Mensaje de presentación',             'description' => 'Se envió mensaje por WhatsApp con información de servicios mineros.', 'days_offset' => -9,  'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 2,  'type' => 'call',    'subject' => 'Cierre de venta - Riego',             'description' => 'Cliente aprobó la compra del sistema de riego inteligente.', 'days_offset' => -28, 'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 2,  'type' => 'note',    'subject' => 'Contrato firmado',                    'description' => 'Se firmó el contrato por Bs 28.000. Instalación programada para el mes que viene.', 'days_offset' => -25, 'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 3,  'type' => 'task',    'subject' => 'Preparar propuesta técnica',           'description' => 'Elaborar propuesta técnica para el edificio residencial.', 'days_offset' => -3,  'completed' => false],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 5,  'type' => 'meeting','subject' => 'Demo equipamiento médico',             'description' => 'Se realizó demostración del equipamiento en las instalaciones del hospital.', 'days_offset' => -11, 'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Deal', 'related_idx' => 5,  'type' => 'whatsapp','subject' => 'Seguimiento post-demo',               'description' => 'Cliente solicitó tiempo para evaluar la propuesta.', 'days_offset' => -5,  'completed' => false],
            // Contact activities
            ['related_type' => 'Aero\\Crm\\Models\\Contact', 'related_idx' => 4, 'type' => 'call',   'subject' => 'Llamada de cortesía',                 'description' => 'Se llamó para agradecer la asistencia al evento.', 'days_offset' => -20, 'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Contact', 'related_idx' => 8, 'type' => 'email',  'subject' => 'Bienvenida al newsletter',            'description' => 'Se agregó al newsletter mensual de la empresa.', 'days_offset' => -15, 'completed' => true],
            ['related_type' => 'Aero\\Crm\\Models\\Contact', 'related_idx' => 9, 'type' => 'whatsapp','subject' => 'Consulta sobre servicios',            'description' => 'Cliente preguntó por precios de consultoría.', 'days_offset' => -4,  'completed' => false],
            ['related_type' => 'Aero\\Crm\\Models\\Contact', 'related_idx' => 12,'type' => 'meeting','subject' => 'Capacitación inicial',                'description' => 'Se programó capacitación para el equipo del cliente.', 'days_offset' => -1,  'completed' => false],
        ];

        foreach ($activities as $a) {
            $relatedId = $a['related_type'] === 'Aero\\Crm\\Models\\Deal'
                ? $dealIds[$a['related_idx']]
                : $contactIds[$a['related_idx']];

            DB::table('aero_crm_activities')->insertGetId([
                'tenant_id'    => $tenantId,
                'related_type' => $a['related_type'],
                'related_id'   => $relatedId,
                'type'         => $a['type'],
                'subject'      => $a['subject'],
                'description'  => $a['description'],
                'due_at'       => $a['completed'] ? null : Carbon::now()->addDays(rand(1, 7))->toDateTimeString(),
                'completed_at' => $a['completed'] ? Carbon::now()->addDays($a['days_offset'])->toDateTimeString() : null,
                'owner_id'     => $ownerId,
                'created_at'   => Carbon::now()->addDays($a['days_offset']),
                'updated_at'   => now(),
            ]);
        }

        // ── 7. Teams ────────────────────────────────────────────────────
        $teamVentasId = DB::table('aero_crm_teams')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => 'Equipo Ventas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teamSoporteId = DB::table('aero_crm_teams')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => 'Equipo Soporte',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign owner to Ventas team
        DB::table('aero_crm_team_members')->insert([
            'team_id'    => $teamVentasId,
            'user_id'    => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── 8. Contact Lists ────────────────────────────────────────────
        $listPremiumId = DB::table('aero_crm_contact_lists')->insertGetId([
            'tenant_id'   => $tenantId,
            'name'        => 'Clientes Premium',
            'description' => 'Contactos con alto potencial de compra y facturación superior a Bs 20.000.',
            'color'       => '#f59e0b',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $listProveedoresId = DB::table('aero_crm_contact_lists')->insertGetId([
            'tenant_id'   => $tenantId,
            'name'        => 'Proveedores Estratégicos',
            'description' => 'Contactos clave de proveedores con los que tenemos contratos activos.',
            'color'       => '#3b82f6',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $listEventosId = DB::table('aero_crm_contact_lists')->insertGetId([
            'tenant_id'   => $tenantId,
            'name'        => 'Asistentes Evento TechSummit 2025',
            'description' => 'Contactos captados en el evento TechSummit Bolivia 2025.',
            'color'       => '#22c55e',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Pivot contacts <-> lists
        $pivotData = [
            // Premium: contacts 0, 1, 2, 5, 10, 11, 13
            [$listPremiumId, $contactIds[0]],
            [$listPremiumId, $contactIds[1]],
            [$listPremiumId, $contactIds[2]],
            [$listPremiumId, $contactIds[5]],
            [$listPremiumId, $contactIds[10]],
            [$listPremiumId, $contactIds[11]],
            [$listPremiumId, $contactIds[13]],
            // Proveedores: contacts 3, 7, 12
            [$listProveedoresId, $contactIds[3]],
            [$listProveedoresId, $contactIds[7]],
            [$listProveedoresId, $contactIds[12]],
            // Evento: contacts 4, 8, 9, 12, 14
            [$listEventosId, $contactIds[4]],
            [$listEventosId, $contactIds[8]],
            [$listEventosId, $contactIds[9]],
            [$listEventosId, $contactIds[12]],
            [$listEventosId, $contactIds[14]],
        ];

        foreach ($pivotData as [$listId, $contactId]) {
            DB::table('aero_crm_contact_list_contact')->insert([
                'contact_list_id' => $listId,
                'contact_id'      => $contactId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // ── 9. Collection Items ─────────────────────────────────────────
        $collectionItems = [
            ['concept' => 'Licencia ERP - Anticipo 50%',          'amount' => 7500.00,  'contact_idx' => 0,  'list_id' => $listPremiumId, 'days_offset' => -5,  'status' => 'pending'],
            ['concept' => 'Consultoría Minera - Cuota 1',         'amount' => 15000.00, 'contact_idx' => 1,  'list_id' => $listPremiumId, 'days_offset' => -10, 'status' => 'paid'],
            ['concept' => 'Sistema de Riego - Pago final',        'amount' => 14000.00, 'contact_idx' => 2,  'list_id' => $listPremiumId, 'days_offset' => 3,   'status' => 'pending'],
            ['concept' => 'Obra Edificio - Anticipo',             'amount' => 40000.00, 'contact_idx' => 3,  'list_id' => $listProveedoresId, 'days_offset' => -15, 'status' => 'pending'],
            ['concept' => 'GPS Flotillas - Mantenimiento anual',  'amount' => 4500.00,  'contact_idx' => 7,  'list_id' => $listProveedoresId, 'days_offset' => 7,   'status' => 'pending'],
            ['concept' => 'Soporte TI - Mensualidad enero',       'amount' => 1000.00,  'contact_idx' => 10, 'list_id' => $listPremiumId, 'days_offset' => -30, 'status' => 'paid'],
            ['concept' => 'Soporte TI - Mensualidad febrero',     'amount' => 1000.00,  'contact_idx' => 10, 'list_id' => $listPremiumId, 'days_offset' => 0,   'status' => 'pending'],
            ['concept' => 'Auditoría Ambiental - Cuota 2',        'amount' => 12000.00, 'contact_idx' => 11, 'list_id' => $listPremiumId, 'days_offset' => 10,  'status' => 'pending'],
            ['concept' => 'Marketing Digital Q4 - Primer pago',   'amount' => 3166.67,  'contact_idx' => 9,  'list_id' => $listEventosId, 'days_offset' => -2,  'status' => 'pending'],
            ['concept' => 'Monitor Paciente - Cuota 1',           'amount' => 18000.00, 'contact_idx' => 13, 'list_id' => $listPremiumId, 'days_offset' => 5,   'status' => 'pending'],
        ];

        $collectionItemIds = [];
        foreach ($collectionItems as $ci) {
            $collectionItemIds[] = DB::table('aero_crm_collection_items')->insertGetId([
                'tenant_id'      => $tenantId,
                'contact_id'     => $contactIds[$ci['contact_idx']],
                'contact_list_id'=> $ci['list_id'],
                'owner_id'       => $ownerId,
                'concept'        => $ci['concept'],
                'amount'         => $ci['amount'],
                'currency'       => 'BOB',
                'due_date'       => Carbon::now()->addDays($ci['days_offset'])->toDateString(),
                'status'         => $ci['status'],
                'paid_at'        => $ci['status'] === 'paid' ? Carbon::now()->addDays($ci['days_offset'])->toDateTimeString() : null,
                'reminder_count' => 0,
                'notes'          => null,
                'created_at'     => Carbon::now()->addDays($ci['days_offset'] - 3),
                'updated_at'     => now(),
            ]);
        }

        // ── 10. Collection Reminder Rules ───────────────────────────────
        $rulePreVencimientoId = DB::table('aero_crm_collection_reminder_rules')->insertGetId([
            'tenant_id'          => $tenantId,
            'contact_list_id'    => $listPremiumId,
            'name'               => 'Recordatorio pre-vencimiento',
            'offset_days'        => null,
            'start_days_before'  => 3,
            'frequency_days'     => 1,
            'message_template'   => 'Estimado/a {contact_name}, le recordamos que su pago de {amount} {currency} por "{concept}" vence el {due_date}. Por favor realice su pago a tiempo.',
            'active'             => true,
            'sort_order'         => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $rulePostVencimientoId = DB::table('aero_crm_collection_reminder_rules')->insertGetId([
            'tenant_id'          => $tenantId,
            'contact_list_id'    => null,
            'name'               => 'Cobranza post-vencimiento',
            'offset_days'        => null,
            'start_days_before'  => 0,
            'frequency_days'     => 2,
            'message_template'   => 'Estimado/a {contact_name}, su pago de {amount} {currency} por "{concept}" se encuentra vencido desde {due_date}. Le solicitamos regularizar su situación a la brevedad.',
            'active'             => true,
            'sort_order'         => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // ── 11. CRM Settings ───────────────────────────────────────────
        DB::table('aero_crm_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'is_enabled'               => true,
                'collections_enabled'      => true,
                'reminder_interval_days'   => 2,
                'reminder_message_template'=> 'Le recordamos que su pago vence pronto.',
                'collections_bank_account_id' => null,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]
        );

        // ── 12. Assign deals to team ────────────────────────────────────
        DB::table('aero_crm_deals')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_slice($dealIds, 0, 5))
            ->update(['team_id' => $teamVentasId]);
    }

    public function down(): void
    {
        $tenantId = DB::table('aero_sites_tenants')->orderBy('id')->value('id');
        if (!$tenantId) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'aero_crm_collection_reminder_logs',
            'aero_crm_collection_reminder_rules',
            'aero_crm_collection_items',
            'aero_crm_contact_list_contact',
            'aero_crm_contact_lists',
            'aero_crm_activities',
            'aero_crm_deals',
            'aero_crm_leads',
            'aero_crm_pipeline_stages',
            'aero_crm_pipelines',
            'aero_crm_team_members',
            'aero_crm_teams',
            'aero_crm_contacts',
            'aero_crm_companies',
            'aero_crm_settings',
        ];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->where('tenant_id', $tenantId)->delete();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
