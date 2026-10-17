<?php namespace Aero\Plan\Tests;

use Aero\Plan\Classes\PlanService;
use Aero\Plan\Classes\Stages;
use Aero\Plan\Models\Plan;
use ApplicationException;
use PluginTestCase;

class FlowTest extends PluginTestCase
{
    protected function step1Answers(): array
    {
        return [
            'negocio' => 'Tienda Don Mateo', 'rubro' => 'Comercio o tienda',
            'perfil' => 'Ya opero y quiero ordenar mi trabajo', 'hoy' => ['WhatsApp', 'Cuaderno o papel'],
            'dolor' => 'Pierdo pedidos', 'meta' => 'Cobrar con QR', 'contacto' => 'Mateo, tardes',
            'rq0' => 'Unos 300 productos', 'rq1' => 'Efectivo y QR', 'rq2' => 'Sí, por WhatsApp',
        ];
    }

    public function testStartCreatesFiveStepsWithOnlyTheFirstOpen(): void
    {
        $plan = PlanService::start(10);

        $this->assertCount(5, $plan->steps);
        $this->assertSame(['open', 'locked', 'locked', 'locked', 'locked'], $plan->steps->pluck('status')->all());
        $this->assertSame($plan->id, PlanService::start(10)->id, 'Un solo plan por tenant');
    }

    public function testCannotSubmitWithMissingAnswers(): void
    {
        $step = PlanService::start(10)->steps->first();
        PlanService::saveClient($step, ['negocio' => 'Solo el nombre']);

        $this->expectException(ApplicationException::class);
        PlanService::submit($step->fresh());
    }

    public function testRubroAddsKeyQuestionsAndFullFlowApprovesAndOpensNext(): void
    {
        $plan = PlanService::start(10);
        $step = $plan->steps->first();

        PlanService::saveClient($step, ['rubro' => 'Gimnasio o deportes']);
        $labels = array_column(Stages::fields(1, $step->fresh()->answers), 'l');
        $this->assertContains('¿Ofreces clases con cupo limitado?', $labels);

        PlanService::saveClient($step->fresh(), $this->step1Answers() + ['rubro' => 'Comercio o tienda']);
        $this->assertSame([], Stages::missing(1, $step->fresh()->answers));

        PlanService::submit($step->fresh());
        $this->assertSame('review', $step->fresh()->status);

        // En revisión el dueño ya no edita.
        try {
            PlanService::saveClient($step->fresh(), ['negocio' => 'Otro']);
            $this->fail('Debió rechazar la edición en revisión');
        } catch (ApplicationException $e) {
            $this->assertStringContainsString('no se puede editar', $e->getMessage());
        }

        PlanService::approve($step->fresh(), null, 'Todo claro');
        $this->assertSame('approved', $step->fresh()->status);
        $this->assertSame('open', $plan->steps()->where('number', 2)->first()->status);
    }

    public function testRequestChangesReopensForEditingAndNeedsAComment(): void
    {
        $step = PlanService::start(10)->steps->first();
        PlanService::saveClient($step, $this->step1Answers());
        PlanService::submit($step->fresh());

        try {
            PlanService::requestChanges($step->fresh(), null, '  ');
            $this->fail('Debió exigir un comentario');
        } catch (ApplicationException $e) {
            $this->assertSame('review', $step->fresh()->status);
        }

        PlanService::requestChanges($step->fresh(), null, 'Aclara qué vendes');
        $this->assertSame('changes', $step->fresh()->status);

        PlanService::saveClient($step->fresh(), ['dolor' => 'Más detalle'] + $this->step1Answers());
        $this->assertSame('Más detalle', $step->fresh()->answer('dolor'));
    }

    public function testClientCannotWriteTeamFieldsOrInvalidOptions(): void
    {
        $plan = PlanService::start(10);
        $step2 = $plan->steps()->where('number', 2)->first();
        $step2->update(['status' => 'open']);

        PlanService::saveTeam($step2->fresh(), ['alcance' => 'Sitio y cobros QR']);
        PlanService::saveClient($step2->fresh(), ['alcance' => 'hackeado', 'acepto' => '1', 'usuarios' => 'Mil millones', 'modulos' => ['Sitio web', 'Inventado']]);

        $a = $step2->fresh()->answers;
        $this->assertSame('Sitio y cobros QR', $a['alcance'], 'El dueño no puede tocar lo del equipo');
        $this->assertTrue($a['acepto']);
        $this->assertSame('', $a['usuarios']);
        $this->assertSame(['Sitio web'], $a['modulos']);
    }

    public function testCannotAcceptScopeBeforeTeamWritesIt(): void
    {
        $plan = PlanService::start(10);
        $step2 = $plan->steps()->where('number', 2)->first();
        $step2->update(['status' => 'open']);

        PlanService::saveClient($step2->fresh(), ['acepto' => '1']);

        $this->assertFalse($step2->fresh()->answer('acepto'));
    }

    public function testUrlFieldRejectsNonHttpLinks(): void
    {
        $plan = PlanService::start(10);
        $step3 = $plan->steps()->where('number', 3)->first();
        $step3->update(['status' => 'open']);

        $this->expectException(ApplicationException::class);
        PlanService::saveClient($step3->fresh(), ['marca' => 'javascript:alert(1)']);
    }

    public function testPauseStopsTheDayCounterAndResumeKeepsProgress(): void
    {
        $plan = PlanService::start(10);
        $plan->update(['started_at' => now()->subDays(15)]);
        $this->assertSame(16, $plan->fresh()->day);

        PlanService::pause($plan->fresh(), 'No es el momento');
        $plan->refresh();
        $this->assertSame('paused', $plan->status);

        // Se pausó hace 10 días (día 6 del plan) y sigue pausado: el contador no avanzó.
        $plan->update(['paused_at' => now()->subDays(10)]);
        $this->assertSame(6, $plan->fresh()->day, 'En pausa el día no avanza');

        PlanService::resume($plan->fresh());
        $plan->refresh();
        $this->assertSame('active', $plan->status);
        $this->assertGreaterThanOrEqual(10 * 86400, (int) $plan->paused_seconds);
        $this->assertSame('open', $plan->steps->first()->status, 'El avance se conserva');
    }

    public function testPausedPlanRejectsEdits(): void
    {
        $plan = PlanService::start(10);
        $step = $plan->steps->first();
        PlanService::pause($plan);

        $this->expectException(ApplicationException::class);
        PlanService::saveClient($step->fresh(), ['negocio' => 'x']);
    }

    public function testLastApprovalCompletesThePlan(): void
    {
        $plan = PlanService::start(10);
        foreach ($plan->steps as $s) {
            $s->update(['status' => $s->number === 5 ? 'review' : 'approved']);
        }

        PlanService::approve($plan->steps()->where('number', 5)->first(), null);

        $this->assertSame('completed', $plan->fresh()->status);
        $this->assertNotNull($plan->fresh()->completed_at);
    }

    public function testPlansAreIsolatedPerTenant(): void
    {
        $a = PlanService::start(10);
        $b = PlanService::start(20);

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame($a->id, PlanService::forTenant(10)->id);
        $this->assertSame($b->id, PlanService::forTenant(20)->id);
        $this->assertNull(PlanService::forTenant(30));
        $this->assertNull(PlanService::forTenant(null), 'Sin tenant no se resuelve ningún plan (falla cerrado)');

        $this->assertSame(1, Plan::where('tenant_id', 10)->count());
        $this->assertTrue($a->steps->every(fn ($s) => $s->tenant_id === 10));
        $this->assertTrue($a->messages->every(fn ($m) => $m->tenant_id === 10));
    }

    public function testMessagesNeedAnOpenStep(): void
    {
        $plan = PlanService::start(10);

        $this->assertNotNull(PlanService::post($plan, 1, 'client', null, 'Hola'));
        $this->assertNull(PlanService::post($plan, 1, 'client', null, '   '));

        $this->expectException(ApplicationException::class);
        PlanService::post($plan, 3, 'client', null, 'Paso bloqueado');
    }
}
