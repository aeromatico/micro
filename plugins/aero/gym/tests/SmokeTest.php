<?php namespace Aero\Gym\Tests;

use Aero\Gym\Classes\AccessControl;
use Aero\Gym\Classes\BookingService;
use Aero\Gym\Classes\GymException;
use Aero\Gym\Classes\MembershipService;
use Aero\Gym\Models\Booking;
use Aero\Gym\Models\ClassSession;
use Aero\Gym\Models\ClassType;
use Aero\Gym\Models\Member;
use Aero\Gym\Models\Membership;
use Aero\Gym\Models\Plan;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    protected function member(int $tenant, string $name = 'Ana', bool $withMembership = true): Member
    {
        $m = Member::create(['tenant_id' => $tenant, 'name' => $name, 'status' => 'active']);
        if ($withMembership) {
            $plan = Plan::firstOrCreate(['tenant_id' => $tenant, 'name' => 'Mensual'], ['price' => 100, 'duration_days' => 30]);
            app(MembershipService::class)->create($m, $plan, null, true);
        }

        return $m;
    }

    protected function makeSession(int $tenant, int $capacity = 2): ClassSession
    {
        $type = ClassType::create(['tenant_id' => $tenant, 'name' => 'Yoga', 'duration_minutes' => 60, 'default_capacity' => $capacity]);

        return ClassSession::create(['tenant_id' => $tenant, 'class_type_id' => $type->id, 'starts_at' => now()->addDay(), 'capacity' => $capacity]);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['members', 'plans', 'memberships', 'groups', 'group_member', 'instructors', 'class_types', 'schedules', 'sessions', 'bookings', 'access_logs', 'routines', 'routine_items', 'settings'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_gym_' . $t), "Falta aero_gym_{$t}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Gym\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            foreach (($item['permissions'] ?? []) as $p) {
                $this->assertContains($p, $declared);
            }
            foreach (($item['sideMenu'] ?? []) as $key => $side) {
                if ($key === 'shop') {
                    continue; // permiso de Aero.Shop
                }
                foreach (($side['permissions'] ?? []) as $p) {
                    $this->assertContains($p, $declared);
                }
            }
        }
    }

    public function testBookingFillsCapacityThenWaitlistAndPromotes(): void
    {
        $s = $this->makeSession(1, 2);
        [$a, $b, $c] = [$this->member(1, 'A'), $this->member(1, 'B'), $this->member(1, 'C')];
        $svc = app(BookingService::class);

        $this->assertSame('booked', $svc->book($a, $s)->status);
        $bb = $svc->book($b, $s);
        $this->assertSame('booked', $bb->status);
        $wait = $svc->book($c, $s);
        $this->assertSame('waitlist', $wait->status);

        $svc->cancel($bb, false);
        $this->assertSame('booked', $wait->fresh()->status, 'La lista de espera debe subir sola.');
        $this->assertSame(2, $s->fresh()->bookedCount());
    }

    public function testCannotBookTwiceOrWithoutMembershipOrAcrossTenants(): void
    {
        $s = $this->makeSession(1);
        $svc = app(BookingService::class);
        $a = $this->member(1);
        $svc->book($a, $s);

        try {
            $svc->book($a, $s);
            $this->fail('Doble reserva permitida');
        } catch (GymException $e) {
            $this->assertStringContainsString('ya está inscrito', $e->getMessage());
        }

        $this->expectException(GymException::class);
        $svc->book($this->member(1, 'Sin plan', false), $s);
    }

    public function testBookingAcrossTenantsIsRejected(): void
    {
        $s = $this->makeSession(1);
        $this->expectException(GymException::class);
        app(BookingService::class)->book($this->member(2), $s);
    }

    public function testAccessControlRespectsMembershipAndTenant(): void
    {
        $ctl = app(AccessControl::class);
        $m = $this->member(1);

        $this->assertTrue($ctl->check(1, $m->qr_token)['granted']);
        $this->assertFalse($ctl->check(2, $m->qr_token)['granted'], 'El QR de otro gimnasio no abre.');

        Membership::where('member_id', $m->id)->update(['ends_on' => today()->subDay(), 'status' => 'active']);
        $r = $ctl->check(1, $m->qr_token);
        $this->assertFalse($r['granted']);
        $this->assertSame('Sin membresía vigente', $r['reason']);

        $this->assertSame(3, \Aero\Gym\Models\AccessLog::where('tenant_id', '>', 0)->count());
    }

    public function testExpireDueAndPaidActivation(): void
    {
        $m = $this->member(1, 'Luis', false);
        $plan = Plan::create(['tenant_id' => 1, 'name' => 'Semanal', 'price' => 30, 'duration_days' => 7]);
        $svc = app(MembershipService::class);

        $ms = $svc->create($m, $plan);
        $this->assertSame('pending', $ms->status);
        $this->assertNull($m->currentMembership());

        $svc->markPaid($ms);
        $this->assertNotNull($m->currentMembership());

        Membership::whereKey($ms->id)->update(['starts_on' => today()->subDays(9), 'ends_on' => today()->subDays(2)]);
        $this->assertSame(1, $svc->expireDue());
        $this->assertSame('expired', $ms->fresh()->status);
    }

    public function testTenantVisibilityFailsClosedWithoutTenant(): void
    {
        $this->member(1);
        $this->member(2);

        $this->assertSame(1, Member::forTenant(1)->count());
        $this->assertSame(0, Member::visible()->count(), 'Sin tenant resoluble no se ve nada.');
    }
}
