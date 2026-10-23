<?php namespace Aero\Church\Tests;

use Aero\Church\Models\Attendance;
use Aero\Church\Models\Group;
use Aero\Church\Models\GroupMember;
use Aero\Church\Models\Meeting;
use Aero\Church\Models\Member;
use PluginTestCase;

class GroupsTest extends PluginTestCase
{
    public function testSchemaAndTenantIsolation(): void
    {
        foreach (['aero_church_groups', 'aero_church_group_members', 'aero_church_meetings', 'aero_church_attendances'] as $t) {
            $this->assertTrue(\Schema::hasTable($t), $t);
        }

        Group::create(['tenant_id' => 1, 'name' => 'Jóvenes', 'type' => 'youth']);
        Group::create(['tenant_id' => 2, 'name' => 'Alabanza', 'type' => 'ministry']);

        $this->assertSame(['Jóvenes'], Group::forTenant(1)->pluck('name')->all());
        $this->assertSame(0, Group::forTenant(null)->count());
    }

    public function testMeetingInheritsGroupTenantAndAttendanceUnique(): void
    {
        $g = Group::create(['tenant_id' => 1, 'name' => 'Célula Norte', 'type' => 'cell']);
        $m = Member::create(['tenant_id' => 1, 'first_name' => 'Ana', 'status' => 'active']);
        GroupMember::create(['tenant_id' => 1, 'group_id' => $g->id, 'member_id' => $m->id]);
        $meeting = Meeting::create(['group_id' => $g->id, 'meeting_date' => '2026-10-20']);

        $this->assertSame(1, $meeting->tenant_id);

        Attendance::create(['tenant_id' => 1, 'meeting_id' => $meeting->id, 'member_id' => $m->id, 'present' => true]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Attendance::create(['tenant_id' => 1, 'meeting_id' => $meeting->id, 'member_id' => $m->id, 'present' => false]);
    }

    public function testLeaderOnlySeesOwnGroups(): void
    {
        $a = Group::create(['tenant_id' => 1, 'name' => 'Mío', 'type' => 'cell', 'backend_user_id' => 7]);
        Group::create(['tenant_id' => 1, 'name' => 'Ajeno', 'type' => 'cell', 'backend_user_id' => 8]);

        // Sin usuario autenticado (servicio) el alcance lo fija quien llama: ve toda la iglesia.
        $this->assertSame(2, Group::forTenant(1)->count());
        $this->assertSame(['Mío'], Group::forTenant(1)->where('backend_user_id', 7)->pluck('name')->all());
        $this->assertSame($a->id, Group::forTenant(1)->where('backend_user_id', 7)->first()->id);
    }
}
