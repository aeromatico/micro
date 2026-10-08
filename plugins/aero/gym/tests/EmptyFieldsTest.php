<?php namespace Aero\Gym\Tests;

use Aero\Gym\Models\ClassType;
use Aero\Gym\Models\Member;
use Aero\Gym\Models\Plan;
use Aero\Gym\Models\Routine;
use Aero\Gym\Models\Schedule;
use PluginTestCase;

/** Los formularios mandan '' en lo vacío: debe guardarse como NULL, no romper con un error SQL. */
class EmptyFieldsTest extends PluginTestCase
{
    public function testEmptyStringsBecomeNull(): void
    {
        $type = ClassType::create(['tenant_id' => 1, 'name' => 'Zumba', 'duration_minutes' => 60, 'default_capacity' => 20, 'page_id' => '', 'color' => '#f2ff00']);
        $this->assertNull($type->fresh()->page_id);

        $member = Member::create(['tenant_id' => 1, 'name' => 'Ana', 'birthdate' => '']);
        $this->assertNull($member->fresh()->birthdate);

        $plan = Plan::create(['tenant_id' => 1, 'name' => 'Mensual', 'price' => 10, 'duration_days' => 30, 'classes_per_week' => '', 'shop_product_id' => '']);
        $this->assertNull($plan->fresh()->classes_per_week);
        $this->assertNull($plan->fresh()->shop_product_id);

        $s = Schedule::create(['tenant_id' => 1, 'class_type_id' => $type->id, 'weekday' => 1, 'start_time' => '18:00', 'instructor_id' => '', 'capacity' => '']);
        $this->assertNull($s->fresh()->instructor_id);
        $this->assertNull($s->fresh()->capacity);

        $r = Routine::create(['tenant_id' => 1, 'name' => 'R', 'member_id' => '', 'instructor_id' => '', 'starts_on' => '', 'ends_on' => '']);
        $this->assertNull($r->fresh()->member_id);
        $this->assertNull($r->fresh()->starts_on);
    }
}
