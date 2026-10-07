<?php namespace Aero\Workspaces\Tests;

use Aero\Workspaces\Models\Hire;
use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\StaffRate;
use Aero\Workspaces\Models\TaskRate;
use October\Rain\Database\ModelException;
use PluginTestCase;

/**
 * Base del catálogo: todo staff tiene tarifa, las tarifas no son negativas,
 * un tenant contrata cada agente una sola vez y las skills se asignan.
 */
class StaffTest extends PluginTestCase
{
    protected function makeStaff(array $overrides = []): Staff
    {
        $staff = new Staff();
        $staff->fill(array_merge([
            'name' => 'Prueba', 'slug' => 'prueba-' . uniqid(), 'role' => 'Coordinadora',
            'kind' => 'ai', 'rarity' => 'ssr', 'is_active' => true,
        ], $overrides));
        $staff->save();

        return $staff;
    }

    public function testTablasExisten(): void
    {
        foreach (['aero_workspaces_staff', 'aero_workspaces_staff_rates', 'aero_workspaces_task_rates', 'aero_workspaces_skills', 'aero_workspaces_staff_skill', 'aero_workspaces_hires'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testTodoStaffNaceConTarifaCero(): void
    {
        $staff = $this->makeStaff();

        $this->assertEquals(0, $staff->hire_fee, 'por defecto no cobra al contratar');
        $this->assertNotNull(StaffRate::where('staff_id', $staff->id)->first());
    }

    public function testTarifaDeContratacionNoPuedeSerNegativa(): void
    {
        $staff = $this->makeStaff(['slug' => 'bruno']);

        $staff->rate->hire_fee = -5;
        $this->expectException(ModelException::class);
        $staff->rate->save();
    }

    public function testTarifaPorTareaNoPuedeSerNegativa(): void
    {
        $staff = $this->makeStaff(['slug' => 'camila']);

        $rate = new TaskRate();
        $rate->fill(['staff_id' => $staff->id, 'task_type' => 'video_clip', 'fee' => -1]);

        $this->expectException(ModelException::class);
        $rate->save();
    }

    public function testTarifaPorTareaCeroEsValida(): void
    {
        $staff = $this->makeStaff(['slug' => 'diego']);

        $rate = new TaskRate();
        $rate->fill(['staff_id' => $staff->id, 'task_type' => 'ilustracion', 'fee' => 0]);

        $this->assertTrue($rate->save());
    }

    public function testKindSoloAdmiteIA(): void
    {
        $staff = new Staff();
        $staff->fill(['name' => 'Persona', 'slug' => 'persona', 'role' => 'Editor', 'kind' => 'human', 'rarity' => 'r']);

        $this->expectException(ModelException::class);
        $staff->save(); // los humanos quedan para una etapa posterior
    }

    public function testUnTenantContrataCadaAgenteUnaVez(): void
    {
        $staff = $this->makeStaff(['slug' => 'sofia']);

        Hire::create(['tenant_id' => 32, 'staff_id' => $staff->id, 'fee_charged' => 0, 'hired_at' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Hire::create(['tenant_id' => 32, 'staff_id' => $staff->id, 'fee_charged' => 0, 'hired_at' => now()]);
    }

    public function testContratacionesSeFiltranPorTenant(): void
    {
        $a = $this->makeStaff(['slug' => 'tomas']);
        $b = $this->makeStaff(['slug' => 'renata']);

        Hire::create(['tenant_id' => 32, 'staff_id' => $a->id, 'fee_charged' => 0, 'hired_at' => now()]);
        Hire::create(['tenant_id' => 40, 'staff_id' => $b->id, 'fee_charged' => 0, 'hired_at' => now()]);

        $this->assertCount(1, Hire::forTenant(32)->get());
        $this->assertSame($a->id, Hire::forTenant(32)->first()->staff_id);
    }

    public function testSkillSeAsignaAStaff(): void
    {
        $staff = $this->makeStaff(['slug' => 'ivan']);

        $skill = new Skill();
        $skill->fill([
            'kind' => 'official', 'name' => 'Análisis SEO', 'slug' => 'analisis-seo',
            'description' => 'Úsala para proponer títulos y etiquetas.',
        ]);
        $skill->save();

        $staff->skills()->attach($skill->id);

        $this->assertCount(1, $staff->fresh()->skills);
    }

    /** Lo que llega del formulario: tarifa de contratación y tarifas por tarea se guardan en el agente. */
    public function testFormularioGuardaTarifasYSincronizaTareas(): void
    {
        $staff = $this->makeStaff(['slug' => 'lucia-form']);

        $staff->fill([
            'name' => 'Lucía Vargas', 'hire_fee' => 40,
            'task_rates' => [
                ['task_type' => 'guion', 'fee' => 5],
                ['task_type' => 'revision', 'fee' => 0],
                ['task_type' => '', 'fee' => 9],
            ],
        ]);
        $staff->save();

        $fresh = Staff::find($staff->id);
        $this->assertEquals(40, $fresh->hire_fee);
        $this->assertCount(2, TaskRate::where('staff_id', $staff->id)->get(), 'las filas sin tipo se descartan');

        // Segundo guardado: reemplaza, no acumula.
        $fresh->fill(['task_rates' => [['task_type' => 'guion', 'fee' => 7]], 'hire_fee' => 0]);
        $fresh->save();

        $again = Staff::find($staff->id);
        $this->assertEquals(0, $again->hire_fee);
        $rows = TaskRate::where('staff_id', $staff->id)->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(7, (float) $rows->first()->fee);
    }

    public function testTarifaDeContratacionNegativaSeConvierteEnCero(): void
    {
        $staff = $this->makeStaff(['slug' => 'negativo']);
        $staff->fill(['hire_fee' => -10]);
        $staff->save();

        $this->assertEquals(0, Staff::find($staff->id)->hire_fee);
    }

    public function testListasVaciasDelFormularioSeGuardanComoJsonValido(): void
    {
        $staff = $this->makeStaff(['slug' => 'vacias', 'tags' => '', 'guide' => '', 'capabilities' => '']);
        $staff->fill(['tags' => '', 'guide' => '']);
        $staff->save();

        $fresh = Staff::find($staff->id);
        $this->assertSame([], $fresh->tags);
        $this->assertSame([], $fresh->guide);
    }
}
