<?php namespace Aero\Gym\Tests;

use Aero\Gym\Classes\Progress;
use Aero\Gym\Models\Measurement;
use Aero\Gym\Models\Member;
use October\Rain\Database\ModelException;
use PluginTestCase;

class ProgressTest extends PluginTestCase
{
    protected function m(array $a = []): Measurement
    {
        $member = Member::firstOrCreate(['tenant_id' => 1, 'name' => 'Ana']);

        return Measurement::create($a + ['tenant_id' => 1, 'member_id' => $member->id, 'measured_on' => today()->subDays(10), 'weight_kg' => 80, 'height_cm' => 180]);
    }

    public function testBmiAndCategories(): void
    {
        $this->assertSame(24.7, Measurement::bmi(80, 180));
        $this->assertNull(Measurement::bmi(80, null));
        $this->assertSame('Bajo peso', Measurement::bmiCategory(17.0)['label']);
        $this->assertSame('Peso normal', Measurement::bmiCategory(22.0)['label']);
        $this->assertSame('Sobrepeso', Measurement::bmiCategory(27.0)['label']);
        $this->assertSame('Obesidad', Measurement::bmiCategory(31.0)['label']);
        $this->assertSame(24.7, $this->m()->bmi);
    }

    public function testHeightIsInheritedFromPreviousMeasurement(): void
    {
        $this->m();
        $second = $this->m(['measured_on' => today()->subDays(2), 'weight_kg' => 78, 'height_cm' => null]);
        $this->assertEquals(180, $second->fresh()->height_cm);
    }

    public function testOneMeasurementPerDayAndValidation(): void
    {
        $this->m();
        try {
            $this->m();
            $this->fail('Dos mediciones el mismo día');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        foreach ([['weight_kg' => 5], ['weight_kg' => 90, 'measured_on' => today()->addDay()], ['weight_kg' => 90, 'body_fat_pct' => 99]] as $bad) {
            try {
                $this->m($bad + ['measured_on' => today()->subDays(rand(20, 40))]);
                $this->fail('Dato inválido aceptado: ' . json_encode($bad));
            } catch (ModelException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testChangesAndChart(): void
    {
        $this->m();
        $this->m(['measured_on' => today()->subDay(), 'weight_kg' => 76.5, 'waist_cm' => 84]);
        $rows = Progress::history(Member::first()->id);
        $c = Progress::changes($rows);

        $this->assertSame(-3.5, $c['weight_kg']['delta']);
        $this->assertArrayNotHasKey('waist_cm', $c, 'Sin dato en la primera medición no hay variación.');
        $this->assertStringContainsString('<svg', Progress::chart([['01/10', 80], ['02/10', 78]], '#000', 'kg'));
        $this->assertSame('', Progress::chart([]));
    }
}
