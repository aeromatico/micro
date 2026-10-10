<?php namespace Aero\Office\Console;

use Aero\Office\Classes\Availability;
use Aero\Office\Classes\BookingService;
use Aero\Office\Classes\CrmLink;
use Aero\Office\Models\Booking;
use Aero\Office\Models\BookingLog;
use Aero\Office\Models\Branch;
use Aero\Office\Models\Customer;
use Aero\Office\Models\OfficeSettings;
use Aero\Office\Models\Service;
use Aero\Office\Models\TimeOff;
use Aero\Office\Models\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** office:demo {tenant} [--fresh] — datos de demostración de un consultorio médico en UN tenant. */
class DemoCommand extends Command
{
    protected $signature = 'office:demo {tenant : id del tenant} {--fresh : borra antes lo de Oficina de ESE tenant}
        {--more= : agrega N reservas (y clientes, profesionales y servicios extra) sobre lo que ya hay}';

    protected $description = 'Carga un consultorio de demostración (sucursales, servicios, doctores, clientes y reservas).';

    public function handle(BookingService $svc): int
    {
        $tid = (int) $this->argument('tenant');
        if (!class_exists(\Aero\Sites\Models\Tenant::class) || !\Aero\Sites\Models\Tenant::find($tid)) {
            $this->error('Tenant no encontrado.');

            return self::FAILURE;
        }

        if ($this->option('more') !== null) {
            CrmLink::$disabled = true;
            \Aero\Office\Classes\Notifier::$muted = true;
            $n = max(1, min(500, (int) $this->option('more')));

            return $this->topUp($tid, $n, $svc);
        }

        $has = Branch::where('tenant_id', $tid)->exists() || Booking::where('tenant_id', $tid)->exists();
        if ($has && !$this->option('fresh')) {
            $this->error('El tenant ya tiene datos de Oficina. Usa --fresh para reemplazarlos (solo los de Oficina de ese tenant).');

            return self::FAILURE;
        }

        \Aero\Office\Classes\Notifier::$muted = true; // sin avisos reales a teléfonos de prueba
        CrmLink::$disabled = true; // el demo no debe crear contactos en el CRM real

        DB::transaction(function () use ($tid, $svc) {
            if ($this->option('fresh')) {
                $this->wipe($tid);
            }
            $this->seed($tid, $svc);
        });

        $this->markDemoReminded($tid);
        $this->info('Listo. Bookings: ' . Booking::where('tenant_id', $tid)->count());

        return self::SUCCESS;
    }

    /** Agrega volumen realista sobre un demo existente: más clientes, un par de profesionales/servicios y N reservas. */
    protected function topUp(int $tid, int $n, BookingService $svc): int
    {
        $branches = Branch::where('tenant_id', $tid)->where('is_active', true)->get();
        if ($branches->isEmpty()) {
            $this->error('Primero carga el demo base: php artisan office:demo ' . $tid);

            return self::FAILURE;
        }
        mt_srand(20261010);
        $central = $branches->first();

        // Profesionales y servicios extra (idempotente por nombre).
        $week = fn ($days, $r) => $this->week($days, $r);
        $extraWorkers = [
            ['Dr. Marco Flores', 'Cardiología', [1, 2, 3, 4, 5], [['08:00', '12:00'], ['14:00', '17:00']]],
            ['Lic. Paola Nina', 'Nutrición', [2, 4, 6], [['09:00', '13:00']]],
        ];
        $workers = Worker::where('tenant_id', $tid)->get()->keyBy('name');
        foreach ($extraWorkers as [$name, $title, $days, $ranges]) {
            if (!isset($workers[$name])) {
                $w = Worker::create(['tenant_id' => $tid, 'name' => $name, 'title' => $title, 'hours' => $week($days, $ranges), 'is_active' => true, 'is_public' => true]);
                $workers[$name] = $w;
                $branches->each(fn ($b) => $b->workers()->syncWithoutDetaching([$w->id]));
            }
        }
        $extraServices = [
            ['Consulta cardiológica', 'Consulta', 40, 10, 300, false, 'Dr. Marco Flores'],
            ['Consulta nutricional', 'Consulta', 45, 10, 200, false, 'Lic. Paola Nina'],
            ['Chequeo médico completo', 'Diagnóstico', 60, 15, 450, true, 'Dra. Ana Rojas'],
        ];
        $services = Service::where('tenant_id', $tid)->get()->keyBy('name');
        foreach ($extraServices as [$name, $cat, $dur, $buf, $price, $appr, $doctor]) {
            if (!isset($services[$name]) && isset($workers[$doctor])) {
                $sv = Service::create(['tenant_id' => $tid, 'name' => $name, 'category' => $cat, 'duration_minutes' => $dur, 'buffer_minutes' => $buf,
                    'price' => $price, 'requires_approval' => $appr, 'is_active' => true, 'is_public' => true]);
                $sv->workers()->syncWithoutDetaching([$workers[$doctor]->id]);
                $branches->each(fn ($b) => $b->services()->syncWithoutDetaching([$sv->id]));
                $services[$name] = $sv;
            }
        }

        // Clientes extra.
        $first = ['Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Carmen', 'José', 'Rosa', 'Miguel', 'Elena', 'Pablo', 'Silvia', 'Fernando', 'Patricia',
                  'Ricardo', 'Gabriela', 'Óscar', 'Daniela', 'Raúl', 'Verónica', 'Héctor', 'Mónica', 'Iván', 'Natalia'];
        $last = ['Mamani', 'Quispe', 'Flores', 'Condori', 'Choque', 'Gutiérrez', 'Rojas', 'Vargas', 'Huanca', 'Apaza', 'Colque', 'Nina',
                 'Ticona', 'Cruz', 'Aguilar', 'Salazar', 'Paredes', 'Cáceres', 'Ramos', 'Torrico'];
        $existing = Customer::where('tenant_id', $tid)->count();
        for ($i = 0; $i < 30; $i++) {
            $name = $first[array_rand($first)] . ' ' . $last[array_rand($last)] . ' ' . $last[array_rand($last)];
            $k = $existing + $i + 1;
            Customer::create(['tenant_id' => $tid, 'name' => $name, 'phone' => '7200' . str_pad((string) $k, 4, '0', STR_PAD_LEFT),
                'email' => $i % 2 === 0 ? 'paciente' . $k . '@example.test' : null, 'document' => (string) (5000000 + $k * 911)]);
        }
        $customers = Customer::where('tenant_id', $tid)->get();

        $svcList = $services->values();
        $made = 0;
        $tries = 0;
        while ($made < $n && $tries < $n * 8) {
            $tries++;
            $service = $svcList[array_rand($svcList->all())];
            $branch = $branches[array_rand($branches->all())];
            if (!$branch->services()->where('aero_office_services.id', $service->id)->exists()) {
                continue;
            }
            $offset = mt_rand(-30, 21);
            $day = now()->addDays($offset)->startOfDay();
            $slots = Availability::slots($service, $branch, null, $day, false);
            $slots = array_values(array_filter($slots, fn ($s) => $offset < 0 || $s['starts_at']->gt(now()->addHours(3))));
            if (!$slots) {
                continue;
            }
            $slot = $slots[array_rand($slots)];
            $customer = $customers[array_rand($customers->all())];

            $past = $slot['starts_at']->lt(now());
            $roll = mt_rand(1, 100);
            if ($past) {
                $final = $roll <= 72 ? 'completed' : ($roll <= 85 ? 'no_show' : 'cancelled');
            } else {
                $final = ($service->requires_approval && $roll <= 60) ? 'pending' : ($roll <= 90 ? 'confirmed' : 'cancelled');
            }

            try {
                $b = $svc->create($customer, [
                    'branch_id' => $branch->id, 'service_id' => $service->id, 'worker_id' => null, 'starts_at' => $slot['starts_at'],
                    'status' => in_array($final, ['completed', 'no_show', 'cancelled'], true) ? 'confirmed' : $final,
                ], $final === 'pending' ? 'public' : 'manual');
            } catch (\Aero\Office\Classes\OfficeException $e) {
                continue;
            }
            match ($final) {
                'completed' => $svc->complete($svc->startService($b)),
                'no_show'   => $svc->markNoShow($b),
                'cancelled' => $svc->cancel($b, 'Cancelada a pedido del cliente', 'staff'),
                default     => null,
            };
            $made++;
        }

        $this->markDemoReminded($tid);
        $this->info("Agregadas {$made} reservas. Totales: " . Booking::where('tenant_id', $tid)->count() . ' reservas, '
            . Customer::where('tenant_id', $tid)->count() . ' clientes, ' . Worker::where('tenant_id', $tid)->count() . ' profesionales, '
            . Service::where('tenant_id', $tid)->count() . ' servicios.');

        return self::SUCCESS;
    }

    /** Los teléfonos del demo son de prueba: ninguna reserva demo debe recibir un recordatorio real. */
    protected function markDemoReminded(int $tid): void
    {
        Booking::where('tenant_id', $tid)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
    }

    protected function wipe(int $tid): void
    {
        $branchIds = Branch::where('tenant_id', $tid)->pluck('id');
        $serviceIds = Service::where('tenant_id', $tid)->pluck('id');
        $workerIds = Worker::where('tenant_id', $tid)->pluck('id');
        DB::table('aero_office_branch_service')->whereIn('branch_id', $branchIds)->delete();
        DB::table('aero_office_branch_worker')->whereIn('branch_id', $branchIds)->delete();
        DB::table('aero_office_service_worker')->whereIn('service_id', $serviceIds)->orWhereIn('worker_id', $workerIds)->delete();
        foreach ([BookingLog::class, Booking::class, TimeOff::class, Customer::class, Worker::class, Service::class, Branch::class, OfficeSettings::class] as $m) {
            $q = $m::where('tenant_id', $tid);
            in_array(\October\Rain\Database\Traits\SoftDelete::class, class_uses_recursive($m)) ? $q->forceDelete() : $q->delete();
        }
    }

    protected function week(array $days, array $ranges): array
    {
        $rows = [];
        foreach ($days as $d) {
            foreach ($ranges as [$a, $b]) {
                $rows[] = ['weekday' => $d, 'start_time' => $a, 'end_time' => $b];
            }
        }

        return $rows;
    }

    protected function seed(int $tid, BookingService $svc): void
    {
        OfficeSettings::updateOrCreate(['tenant_id' => $tid], [
            'business_name' => 'Consultorio Médico Salud Integral', 'industry' => 'Consultorio médico',
            'description' => 'Atención médica general, pediatría y laboratorio clínico. Reserva tu cita en línea.',
            'phone' => '+591 2 2400000', 'email' => 'citas@saludintegral.example', 'address' => 'Av. 6 de Agosto 2170, La Paz',
            'brand_color' => '#0ea5a4', 'currency' => 'BOB', 'enabled' => true, 'public_enabled' => true,
            'min_notice_hours' => 2, 'max_advance_days' => 60, 'slot_step_minutes' => 30, 'cancel_window_hours' => 4,
            'auto_assign_worker' => true,
            'booking_terms' => 'Llega 10 minutos antes. Para cancelar o reprogramar avisa con al menos 4 horas de anticipación.',
        ]);

        $central = Branch::create(['tenant_id' => $tid, 'name' => 'Sede Central — Sopocachi', 'address' => 'Av. 6 de Agosto 2170, La Paz', 'phone' => '+591 2 2400000',
            'hours' => array_merge($this->week([1, 2, 3, 4, 5], [['08:00', '12:00'], ['14:00', '18:00']]), $this->week([6], [['09:00', '13:00']]))]);
        $sur = Branch::create(['tenant_id' => $tid, 'name' => 'Sucursal Sur — Calacoto', 'address' => 'Calle 21 de Calacoto 8100, La Paz', 'phone' => '+591 2 2790000',
            'hours' => $this->week([1, 2, 3, 4, 5], [['09:00', '13:00'], ['15:00', '19:00']])]);

        $svcs = [];
        foreach ([
            ['Consulta general', 'Consulta', 'Evaluación médica general y orientación.', 30, 5, 150, false],
            ['Control pediátrico', 'Consulta', 'Control de crecimiento y desarrollo infantil.', 30, 10, 180, false],
            ['Electrocardiograma', 'Diagnóstico', 'Registro de la actividad eléctrica del corazón.', 20, 10, 120, false],
            ['Ecografía abdominal', 'Diagnóstico', 'Requiere aprobación: se confirma según preparación del paciente.', 45, 15, 250, true],
            ['Curación y sutura menor', 'Procedimientos', 'Procedimientos ambulatorios menores.', 30, 15, 200, true],
        ] as [$name, $cat, $desc, $dur, $buf, $price, $appr]) {
            $svcs[$name] = Service::create(['tenant_id' => $tid, 'name' => $name, 'category' => $cat, 'description' => $desc,
                'duration_minutes' => $dur, 'buffer_minutes' => $buf, 'price' => $price, 'requires_approval' => $appr, 'is_active' => true, 'is_public' => true]);
        }
        $hidden = Service::create(['tenant_id' => $tid, 'name' => 'Visita domiciliaria (solo manual)', 'category' => 'Consulta', 'duration_minutes' => 60,
            'buffer_minutes' => 30, 'price' => 400, 'is_active' => true, 'is_public' => false]);

        $docs = [
            'ana' => Worker::create(['tenant_id' => $tid, 'name' => 'Dra. Ana Rojas', 'title' => 'Medicina general', 'phone' => '70010001',
                'bio' => '15 años de experiencia en medicina familiar.']),
            'luis' => Worker::create(['tenant_id' => $tid, 'name' => 'Dr. Luis Mamani', 'title' => 'Pediatría', 'phone' => '70010002',
                'bio' => 'Pediatra con enfoque en crecimiento y vacunación.',
                'hours' => $this->week([1, 3, 5], [['08:00', '12:00']])]),
            'carla' => Worker::create(['tenant_id' => $tid, 'name' => 'Dra. Carla Quispe', 'title' => 'Ecografía y diagnóstico', 'phone' => '70010003',
                'bio' => 'Especialista en diagnóstico por imágenes.', 'hours' => $this->week([2, 4], [['09:00', '13:00'], ['15:00', '19:00']])]),
        ];
        foreach ($docs as $d) {
            $d->update(['is_active' => true, 'is_public' => true]);
        }

        $central->services()->attach(array_merge(array_map(fn ($s) => $s->id, $svcs), [$hidden->id]));
        $sur->services()->attach([$svcs['Consulta general']->id, $svcs['Control pediátrico']->id, $svcs['Electrocardiograma']->id]);
        $central->workers()->attach([$docs['ana']->id, $docs['luis']->id, $docs['carla']->id]);
        $sur->workers()->attach([$docs['ana']->id, $docs['carla']->id]);
        $svcs['Consulta general']->workers()->attach([$docs['ana']->id]);
        $hidden->workers()->attach([$docs['ana']->id]);
        $svcs['Control pediátrico']->workers()->attach([$docs['luis']->id]);
        $svcs['Electrocardiograma']->workers()->attach([$docs['ana']->id, $docs['carla']->id]);
        $svcs['Ecografía abdominal']->workers()->attach([$docs['carla']->id]);
        $svcs['Curación y sutura menor']->workers()->attach([$docs['ana']->id]);

        // Ausencias: feriado general y vacaciones de la pediatra.
        $hol = now()->addDays(9)->startOfDay();
        TimeOff::create(['tenant_id' => $tid, 'type' => 'holiday', 'starts_at' => $hol, 'ends_at' => $hol->copy()->endOfDay(), 'reason' => 'Feriado — cierre del consultorio']);
        TimeOff::create(['tenant_id' => $tid, 'worker_id' => $docs['luis']->id, 'type' => 'vacation',
            'starts_at' => now()->addDays(12)->startOfDay(), 'ends_at' => now()->addDays(19)->endOfDay(), 'reason' => 'Vacaciones']);
        TimeOff::create(['tenant_id' => $tid, 'worker_id' => $docs['ana']->id, 'branch_id' => $central->id, 'type' => 'break',
            'starts_at' => now()->addDays(4)->setTime(10, 0), 'ends_at' => now()->addDays(4)->setTime(11, 0), 'reason' => 'Reunión médica']);

        // Clientes (ficticios; teléfonos de prueba).
        $names = ['María Fernández', 'Jorge Callisaya', 'Lucía Condori', 'Pedro Alanoca', 'Rosa Choque', 'Marcelo Vargas',
                  'Valeria Poma', 'Diego Limachi', 'Elena Gutiérrez', 'Andrés Mendoza', 'Sofía Villca', 'Camila Torrez'];
        $customers = [];
        foreach ($names as $i => $n) {
            $customers[] = Customer::create(['tenant_id' => $tid, 'name' => $n, 'phone' => '7100' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'email' => $i % 3 === 0 ? 'cliente' . ($i + 1) . '@example.test' : null,
                'document' => (string) (4000000 + $i * 1371), 'notes' => $i === 2 ? 'Alergia a la penicilina.' : null]);
        }

        // Reservas: pasadas (completadas / no asistió / canceladas) y futuras (confirmadas / pendientes).
        $plan = [
            // [días, servicio, doctor|null, estado final, origen]
            [-9, 'Consulta general', 'ana', 'completed'], [-8, 'Control pediátrico', 'luis', 'completed'], [-7, 'Electrocardiograma', 'ana', 'completed'],
            [-6, 'Consulta general', 'ana', 'no_show'], [-5, 'Ecografía abdominal', 'carla', 'completed'], [-4, 'Control pediátrico', 'luis', 'cancelled'],
            [-3, 'Consulta general', 'ana', 'completed'], [-2, 'Electrocardiograma', 'carla', 'completed'], [-1, 'Consulta general', 'ana', 'no_show'],
            [0, 'Consulta general', 'ana', 'confirmed'], [0, 'Control pediátrico', 'luis', 'confirmed'], [1, 'Electrocardiograma', null, 'confirmed'],
            [1, 'Consulta general', 'ana', 'confirmed'], [2, 'Ecografía abdominal', 'carla', 'pending'], [2, 'Control pediátrico', 'luis', 'confirmed'],
            [3, 'Curación y sutura menor', 'ana', 'pending'], [3, 'Consulta general', 'ana', 'confirmed'], [5, 'Ecografía abdominal', 'carla', 'pending'],
            [6, 'Consulta general', 'ana', 'confirmed'], [7, 'Electrocardiograma', null, 'confirmed'], [8, 'Control pediátrico', 'luis', 'cancelled'],
        ];
        $made = 0;
        foreach ($plan as $i => [$offset, $svcName, $docKey, $final]) {
            $service = $svcs[$svcName];
            $worker = $docKey ? $docs[$docKey] : null;
            $branch = $central;
            // Si ese día el profesional no atiende (o es feriado), se corre al siguiente día con horarios.
            $slots = [];
            for ($shift = 0; $shift < 7 && !$slots; $shift++) {
                $day = now()->addDays($offset + ($offset < 0 ? -$shift : $shift))->startOfDay();
                $slots = Availability::slots($service, $branch, $worker, $day, false);
                $slots = array_values(array_filter($slots, fn ($s) => $offset <= 0 || $s['starts_at']->gt(now()->addHours(3))));
            }
            if (!$slots) {
                continue;
            }
            $slot = $slots[($i * 3) % count($slots)];
            $customer = $customers[$i % count($customers)];
            $public = $final === 'pending';
            try {
                $b = $svc->create($customer, [
                    'branch_id' => $central->id, 'service_id' => $service->id, 'worker_id' => $worker?->id,
                    'starts_at' => $slot['starts_at'], 'status' => in_array($final, ['completed', 'no_show', 'cancelled'], true) ? 'confirmed' : $final,
                    'customer_notes' => $i % 4 === 0 ? 'Prefiero horario de la mañana.' : null,
                ], $public ? 'public' : 'manual');
            } catch (\Aero\Office\Classes\OfficeException $e) {
                $this->warn("Omitida #{$i}: " . $e->getMessage());
                continue;
            }
            match ($final) {
                'completed' => $svc->complete($svc->startService($b)),
                'no_show'   => $svc->markNoShow($b),
                'cancelled' => $svc->cancel($b, 'Cancelada a pedido del cliente', 'staff'),
                default     => null,
            };
            $made++;
        }

        $this->line("Negocio listo: 2 sucursales, " . (count($svcs) + 1) . " servicios, 3 profesionales, " . count($customers) . " clientes, {$made} reservas.");
    }
}
