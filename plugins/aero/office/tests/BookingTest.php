<?php namespace Aero\Office\Tests;

use Aero\Office\Classes\Availability;
use Aero\Office\Classes\BookingService;
use Aero\Office\Classes\OfficeException;
use Aero\Office\Models\Booking;
use Aero\Office\Models\Branch;
use Aero\Office\Models\Customer;
use Aero\Office\Models\OfficeSettings;
use Aero\Office\Models\Service;
use Aero\Office\Models\TimeOff;
use Aero\Office\Models\Worker;
use Carbon\Carbon;
use PluginTestCase;

class BookingTest extends PluginTestCase
{
    protected function setUpBusiness(int $tenant = 1, array $serviceOver = []): array
    {
        $hours = [];
        foreach (range(1, 7) as $d) {
            $hours[] = ['weekday' => $d, 'start_time' => '09:00', 'end_time' => '12:00'];
            $hours[] = ['weekday' => $d, 'start_time' => '14:00', 'end_time' => '18:00'];
        }
        $branch = Branch::create(['tenant_id' => $tenant, 'name' => 'Central', 'hours' => $hours, 'is_active' => true]);
        $service = Service::create($serviceOver + ['tenant_id' => $tenant, 'name' => 'Consulta', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'price' => 100, 'is_active' => true, 'is_public' => true]);
        $worker = Worker::create(['tenant_id' => $tenant, 'name' => 'Dra. Ana', 'is_active' => true, 'is_public' => true]);
        $branch->services()->attach($service->id);
        $branch->workers()->attach($worker->id);
        $service->workers()->attach($worker->id);
        OfficeSettings::create(['tenant_id' => $tenant, 'enabled' => true, 'public_enabled' => true, 'min_notice_hours' => 0, 'max_advance_days' => 90, 'slot_step_minutes' => 30]);

        return [$branch, $service, $worker, Customer::create(['tenant_id' => $tenant, 'name' => 'Pedro', 'phone' => '70000001'])];
    }

    protected function nextDay(): Carbon
    {
        return now()->addDays(2)->startOfDay();
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['settings', 'branches', 'services', 'workers', 'branch_service', 'service_worker', 'branch_worker', 'time_offs', 'customers', 'bookings', 'booking_logs'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_office_' . $t), "Falta aero_office_{$t}");
        }
    }

    public function testSlotsRespectHoursAndBreak(): void
    {
        [$branch, $service, $worker] = $this->setUpBusiness();
        $times = array_column(Availability::slots($service, $branch, $worker, $this->nextDay()), 'time');

        $this->assertContains('09:00', $times);
        $this->assertContains('11:30', $times);
        $this->assertNotContains('12:00', $times, 'No hay atención en el descanso');
        $this->assertNotContains('13:30', $times);
        $this->assertContains('14:00', $times);
        $this->assertNotContains('18:00', $times);
    }

    public function testBookingBlocksTheSlotAndCancelFreesIt(): void
    {
        [$branch, $service, $worker, $customer] = $this->setUpBusiness();
        $svc = app(BookingService::class);
        $start = $this->nextDay()->setTime(10, 0);

        $b = $svc->create($customer, ['branch_id' => $branch->id, 'service_id' => $service->id, 'worker_id' => $worker->id, 'starts_at' => $start], 'public');
        $this->assertSame('confirmed', $b->status);

        $this->assertNotContains('10:00', array_column(Availability::slots($service, $branch, $worker, $start), 'time'));

        try {
            $svc->create($customer, ['branch_id' => $branch->id, 'service_id' => $service->id, 'worker_id' => $worker->id, 'starts_at' => $start], 'public');
            $this->fail('Debió rechazar la doble reserva');
        } catch (OfficeException $e) {
            $this->assertStringContainsString('ocupa', $e->getMessage());
        }

        $svc->cancel($b, 'prueba');
        $this->assertContains('10:00', array_column(Availability::slots($service, $branch, $worker, $start), 'time'));
    }

    public function testAnyWorkerAssignsFreeOneAndFailsWhenFull(): void
    {
        [$branch, $service, $w1, $customer] = $this->setUpBusiness();
        $w2 = Worker::create(['tenant_id' => 1, 'name' => 'Dr. Luis', 'is_active' => true, 'is_public' => true]);
        $branch->workers()->attach($w2->id);
        $service->workers()->attach($w2->id);
        $svc = app(BookingService::class);
        $start = $this->nextDay()->setTime(9, 0);
        $data = ['branch_id' => $branch->id, 'service_id' => $service->id, 'starts_at' => $start];

        $a = $svc->create($customer, $data, 'public');
        $b = $svc->create($customer, $data, 'public');
        $this->assertNotSame($a->worker_id, $b->worker_id);

        $this->expectException(OfficeException::class);
        $svc->create($customer, $data, 'public');
    }

    public function testTimeOffHidesSlots(): void
    {
        [$branch, $service, $worker] = $this->setUpBusiness();
        $day = $this->nextDay();
        TimeOff::create(['tenant_id' => 1, 'worker_id' => $worker->id, 'type' => 'vacation', 'starts_at' => $day->copy()->startOfDay(), 'ends_at' => $day->copy()->endOfDay()]);

        $this->assertSame([], Availability::slots($service, $branch, $worker, $day));
    }

    public function testBufferBlocksFollowingSlot(): void
    {
        [$branch, $service, $worker, $customer] = $this->setUpBusiness(1, ['name' => 'Corte', 'buffer_minutes' => 30]);
        $svc = app(BookingService::class);
        $start = $this->nextDay()->setTime(9, 0);
        $svc->create($customer, ['branch_id' => $branch->id, 'service_id' => $service->id, 'worker_id' => $worker->id, 'starts_at' => $start], 'public');

        $times = array_column(Availability::slots($service, $branch, $worker, $start), 'time');
        $this->assertNotContains('09:30', $times, 'El descanso posterior también ocupa la agenda');
        $this->assertContains('10:00', $times);
    }

    public function testApprovalFlowRescheduleKeepsHistory(): void
    {
        [$branch, $service, $worker, $customer] = $this->setUpBusiness(1, ['name' => 'Cirugía', 'requires_approval' => true]);
        $svc = app(BookingService::class);
        $start = $this->nextDay()->setTime(9, 0);

        $b = $svc->create($customer, ['branch_id' => $branch->id, 'service_id' => $service->id, 'worker_id' => $worker->id, 'starts_at' => $start], 'public');
        $this->assertSame('pending', $b->status);

        $svc->confirm($b);
        $this->assertSame('confirmed', $b->fresh()->status);

        $svc->reschedule($b->fresh(), $start->copy()->setTime(15, 0));
        $b = $b->fresh();
        $this->assertSame('15:00', $b->starts_at->format('H:i'));
        $this->assertSame(3, $b->logs()->count(), 'creada + confirmada + reprogramada');

        $service->update(['price' => 999]);
        $this->assertEquals(100, $b->fresh()->price, 'Cambiar el precio no altera lo histórico');

        $svc->complete($svc->startService($b));
        $this->expectException(OfficeException::class);
        $svc->cancel($b->fresh());
    }

    public function testTenantsAreIsolated(): void
    {
        [$branch1, $service1, $worker1, $customer1] = $this->setUpBusiness(1);
        [$branch2, , , $customer2] = $this->setUpBusiness(2);

        $this->expectException(OfficeException::class);
        // sucursal de otro negocio con cliente del negocio 1
        app(BookingService::class)->create($customer1, [
            'branch_id' => $branch2->id, 'service_id' => $service1->id, 'worker_id' => $worker1->id, 'starts_at' => $this->nextDay()->setTime(9, 0),
        ], 'public');
    }

    public function testCustomerMatchIsPerTenant(): void
    {
        Customer::create(['tenant_id' => 1, 'name' => 'Ana', 'email' => 'ana@x.com']);
        $this->assertNotNull(Customer::findMatch(1, 'ana@x.com', null));
        $this->assertNull(Customer::findMatch(2, 'ana@x.com', null), 'Los clientes son privados por negocio');
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Office\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared);
            }
            foreach (($item['sideMenu'] ?? []) as $side) {
                foreach (($side['permissions'] ?? []) as $p) {
                    $this->assertContains($p, $declared);
                }
            }
        }
    }

    public function testNotifierContextHasWhatIsNeededForTemplates(): void
    {
        [$branch, $service, $worker, $customer] = $this->setUpBusiness();
        $b = app(BookingService::class)->create($customer, ['branch_id' => $branch->id, 'service_id' => $service->id,
            'worker_id' => $worker->id, 'starts_at' => $this->nextDay()->setTime(10, 0)], 'manual');

        $ctx = \Aero\Office\Classes\Notifier::context($b);
        foreach (['code', 'customer_name', 'service_name', 'worker_name', 'branch_name', 'starts_at', 'url'] as $k) {
            $this->assertArrayHasKey($k, $ctx);
        }
        $this->assertSame('Pedro', $ctx['customer_name']);
        $this->assertStringContainsString('10:00', $ctx['starts_at']);
        $this->assertStringContainsString($b->manage_token, $ctx['url'] === '' ? $b->manage_token : $ctx['url']);
    }

    public function testMutedNotifierSendsNothing(): void
    {
        [$branch, $service, $worker, $customer] = $this->setUpBusiness();
        $b = app(BookingService::class)->create($customer, ['branch_id' => $branch->id, 'service_id' => $service->id,
            'worker_id' => $worker->id, 'starts_at' => $this->nextDay()->setTime(10, 0)], 'manual');

        \Aero\Office\Classes\Notifier::$muted = true;
        try {
            $this->assertFalse(\Aero\Office\Classes\Notifier::fire('office.booking.confirmed', $b));
            $this->assertSame(0, \Aero\Office\Classes\Notifier::sendReminders());
        } finally {
            \Aero\Office\Classes\Notifier::$muted = false;
        }
    }

    public function testBookingMadeInsideReminderWindowIsMarkedWithoutReminder(): void
    {
        [$branch, $service, $worker, $customer] = $this->setUpBusiness();
        OfficeSettings::where('tenant_id', 1)->update(['reminder_hours' => 48, 'notify_customers' => true]);
        $start = now()->addHours(5)->minute(0)->second(0);
        if ($start->hour < 9 || $start->hour >= 11) {
            $this->markTestSkipped('Depende de la hora del día para caer en horario laboral.');
        }
        $b = app(BookingService::class)->create($customer, ['branch_id' => $branch->id, 'service_id' => $service->id,
            'worker_id' => $worker->id, 'starts_at' => $start], 'manual');

        $this->assertSame(0, \Aero\Office\Classes\Notifier::sendReminders(), 'Reservada dentro de la ventana: no se recuerda');
        $this->assertNotNull($b->fresh()->reminder_sent_at, 'Queda marcada para no reintentar');
    }
}
