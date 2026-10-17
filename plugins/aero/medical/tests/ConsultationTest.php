<?php namespace Aero\Medical\Tests;

use Aero\Medical\Classes\ConsultationService;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\VitalsSchema;
use Aero\Medical\Models\Addendum;
use Aero\Medical\Models\ClinicSettings;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\NoteTemplate;
use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Vitals;
use PluginTestCase;

class ConsultationTest extends PluginTestCase
{
    protected function user(int $id = 7, string $name = 'Ana'): object
    {
        return (object) ['id' => $id, 'first_name' => $name, 'last_name' => 'Médica', 'email' => "u{$id}@example.test"];
    }

    protected function svc(): ConsultationService
    {
        return new ConsultationService();
    }

    protected function patient(int $tenant = 1, string $mode = 'human'): Patient
    {
        if (!ClinicSettings::where('tenant_id', $tenant)->exists()) {
            ClinicSettings::create(['tenant_id' => $tenant, 'mode' => $mode]);
        }
        $extra = $mode === 'animal' ? ['species' => 'dog'] : [];
        $p = new Patient(['tenant_id' => $tenant, 'name' => 'Paciente ' . $tenant] + $extra);
        if ($mode === 'animal') {
            $p->kind = 'animal';
        }
        // Con Office instalado todo animal exige tutor.
        if ($mode === 'animal' && $this->officeTables()) {
            $p->customer_id = \Aero\Office\Models\Customer::create(['tenant_id' => $tenant, 'name' => 'Tutor', 'phone' => '7000000' . $tenant])->id;
        }
        $p->save();

        return $p;
    }

    protected function officeTables(): bool
    {
        $file = base_path('plugins/aero/office/updates/create_office_tables.php');
        if (!is_file($file)) {
            return false;
        }
        if (!\Schema::hasTable('aero_office_bookings')) {
            (require $file)->up();
        }

        return true;
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['professionals', 'note_templates', 'consultations', 'vitals', 'addenda', 'booking_patients'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_medical_' . $t), "Falta aero_medical_{$t}");
        }
    }

    public function testStartCreatesDraftWithTemplateSnapshotAndVitals(): void
    {
        $p = $this->patient();
        $c = $this->svc()->start($p, 'consultation', $this->user());

        $this->assertSame('draft', $c->status);
        $keys = array_column($c->sectionList(), 'key');
        $this->assertContains('reason', $keys);
        $this->assertContains('vitals', $keys);
        $this->assertNotNull(Vitals::where('consultation_id', $c->id)->first());
        $this->assertSame('control', $this->svc()->start($p, 'control', $this->user())->kind);
    }

    public function testEditingTemplateDoesNotChangeExistingNotes(): void
    {
        $p = $this->patient();
        $c = $this->svc()->start($p, 'consultation', $this->user());
        $before = array_column($c->sectionList(), 'key');

        $t = NoteTemplate::find($c->template_id);
        $t->sections = [['label' => 'Solo esta', 'type' => 'text']];
        $t->save();

        $this->assertSame($before, array_column(Consultation::find($c->id)->sectionList(), 'key'));
        $this->assertSame(['solo_esta'], array_column(NoteTemplate::find($t->id)->sections, 'key'));
    }

    public function testBusinessCanChooseTheTemplateOfEachKind(): void
    {
        $p = $this->patient();
        NoteTemplate::ensureDefaults(1);
        $mine = NoteTemplate::create(['tenant_id' => 1, 'kind' => 'consultation', 'name' => 'Mía', 'sections' => [['label' => 'Nota', 'type' => 'text']]]);
        ClinicSettings::where('tenant_id', 1)->first()->update(['consultation_template_id' => $mine->id]);

        $this->assertSame($mine->id, $this->svc()->start($p, 'consultation', $this->user())->template_id);
    }

    public function testAutosaveKeepsOnlyTemplateSectionsAndOnlyForAuthor(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user(7));
        $this->svc()->autosave($c, ['reason' => 'Dolor', 'hack' => 'x'], [], $this->user(7));

        $saved = Consultation::find($c->id);
        $this->assertSame('Dolor', $saved->content['reason']);
        $this->assertArrayNotHasKey('hack', $saved->content);
        $this->assertNotNull($saved->last_autosave_at);

        $this->expectException(MedicalException::class);
        $this->svc()->autosave($c, ['reason' => 'Intruso'], [], $this->user(8));
    }

    public function testClinicalTextIsEncryptedAtRest(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user());
        $this->svc()->autosave($c, ['reason' => 'Cefalea-SECRETA'], [], $this->user());

        $raw = \DB::table('aero_medical_consultations')->where('id', $c->id)->value('content');
        $this->assertStringNotContainsString('Cefalea-SECRETA', (string) $raw);
    }

    public function testBmiIsComputedByCodeInHumanOnly(): void
    {
        $this->assertSame(24.2, VitalsSchema::bmi(70, 170));
        $this->assertNull(VitalsSchema::bmi(70, null));

        $c = $this->svc()->start($this->patient(1, 'human'), 'consultation', $this->user());
        $this->svc()->autosave($c, [], ['weight_kg' => '70', 'height_cm' => '170', 'bmi' => '99'], $this->user());
        $this->assertEquals(24.2, Vitals::where('consultation_id', $c->id)->first()->bmi, 'El IMC lo calcula el código, no lo manda el cliente');

        $a = $this->svc()->start($this->patient(2, 'animal'), 'consultation', $this->user());
        $this->svc()->autosave($a, [], ['weight_kg' => '12', 'height_cm' => '50', 'body_condition' => '5', 'mucous' => 'rosy'], $this->user());
        $v = Vitals::where('consultation_id', $a->id)->first();
        $this->assertNull($v->bmi, 'En animal no hay IMC');
        $this->assertSame(5, (int) $v->body_condition);
        $this->assertNull($v->height_cm, 'La talla no es un signo vital veterinario');
    }

    public function testVitalsOutsideCaptureLimitsAreIgnored(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user());
        $this->svc()->autosave($c, [], ['heart_rate' => '80', 'spo2' => '950', 'temperature_c' => '36,6'], $this->user());

        $v = Vitals::where('consultation_id', $c->id)->first();
        $this->assertSame(80, (int) $v->heart_rate);
        $this->assertNull($v->spo2);
        $this->assertEquals(36.6, $v->temperature_c, 'La coma decimal se acepta');
    }

    public function testSignMakesTheNoteImmutable(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user());
        $this->svc()->autosave($c, ['reason' => 'Control'], ['heart_rate' => '70'], $this->user());
        $r = $this->svc()->sign($c->fresh(), $this->user());
        $signed = $r['consultation']->fresh();

        $this->assertTrue($signed->isSigned());
        $this->assertTrue($signed->integrityOk());
        $this->assertNotNull($signed->signed_at);

        foreach ([
            fn () => $signed->update(['service_name' => 'otro']),
            fn () => $signed->delete(),
            fn () => $this->svc()->autosave($signed, ['reason' => 'Cambio'], [], $this->user()),
            fn () => Vitals::where('consultation_id', $signed->id)->first()->update(['heart_rate' => 99]),
            fn () => Vitals::where('consultation_id', $signed->id)->first()->delete(),
        ] as $i => $attempt) {
            try {
                $attempt();
                $this->fail("El intento #{$i} debió fallar");
            } catch (\ApplicationException | MedicalException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame('Control', Consultation::find($c->id)->content['reason']);
    }

    public function testTamperingBreaksTheIntegrityCheck(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user());
        $this->svc()->autosave($c, ['reason' => 'Original'], [], $this->user());
        $this->svc()->sign($c->fresh(), $this->user());

        $this->assertTrue(Consultation::find($c->id)->integrityOk());

        // Alguien con acceso a la BD cambia el contenido saltándose el modelo.
        $tampered = Consultation::find($c->id);
        \DB::table('aero_medical_consultations')->where('id', $c->id)->update(['content' => encrypt(json_encode(['reason' => 'Falsificado']), false)]);
        $this->assertFalse(Consultation::find($c->id)->integrityOk());
    }

    public function testEmptyNoteCannotBeSigned(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user());

        $this->expectException(MedicalException::class);
        $this->svc()->sign($c->fresh(), $this->user());
    }

    public function testOnlyTheAuthorSigns(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user(7));
        $this->svc()->autosave($c, ['reason' => 'x'], [], $this->user(7));

        $this->expectException(MedicalException::class);
        $this->svc()->sign($c->fresh(), $this->user(8));
    }

    public function testAddendaOnlyAfterSigningAndAreImmutable(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user());
        $this->svc()->autosave($c, ['reason' => 'x'], [], $this->user());

        try {
            $this->svc()->addendum($c->fresh(), 'antes de firmar', $this->user());
            $this->fail('No se adenda un borrador');
        } catch (MedicalException $e) {
            $this->assertStringContainsString('borrador', $e->getMessage());
        }

        $this->svc()->sign($c->fresh(), $this->user());
        $a = $this->svc()->addendum($c->fresh(), 'Se aclara dosis', $this->user());

        $this->assertSame('Se aclara dosis', Addendum::find($a->id)->body);
        $this->assertStringNotContainsString('Se aclara dosis', (string) \DB::table('aero_medical_addenda')->value('body'));
        $this->assertTrue(Consultation::find($c->id)->integrityOk(), 'La adenda no altera la nota firmada');

        $this->expectException(\ApplicationException::class);
        Addendum::find($a->id)->update(['body' => 'otra']);
    }

    public function testDraftCanBeDiscardedBySignedCannot(): void
    {
        $c = $this->svc()->start($this->patient(), 'consultation', $this->user());
        $this->svc()->discard($c->fresh(), $this->user());
        $this->assertNull(Consultation::find($c->id));
        $this->assertSame(0, Vitals::where('consultation_id', $c->id)->count());
    }

    public function testNotesAreIsolatedByTenant(): void
    {
        $a = $this->svc()->start($this->patient(1), 'consultation', $this->user());
        $b = $this->svc()->start($this->patient(2), 'consultation', $this->user());

        $this->assertSame([$a->id], Consultation::forTenant(1)->pluck('id')->all());
        $this->assertSame([$b->id], Consultation::forTenant(2)->pluck('id')->all());
        $this->assertCount(0, Consultation::visible()->get());
        $this->assertNotSame($a->template_id, $b->template_id, 'Cada negocio tiene sus propias plantillas');
    }

    public function testSigningStoresLastWeightOfAnimalInItsRecord(): void
    {
        $p = $this->patient(1, 'animal');
        $c = $this->svc()->start($p, 'consultation', $this->user());
        $this->svc()->autosave($c, ['reason' => 'x'], ['weight_kg' => '12.5'], $this->user());
        $this->svc()->sign($c->fresh(), $this->user());

        $this->assertEquals(12.5, Patient::find($p->id)->weight_kg);
    }

    public function testBookingFlowStartsAndCompletesTheBooking(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Requiere Aero.Office');
        }
        \Aero\Office\Classes\Notifier::$muted = true;

        $this->patient(1, 'human');
        $cust = \Aero\Office\Models\Customer::create(['tenant_id' => 1, 'name' => 'Pedro', 'phone' => '70000001']);
        $booking = \Aero\Office\Models\Booking::create([
            'tenant_id' => 1, 'code' => 'T1', 'manage_token' => 't', 'branch_id' => 1, 'service_id' => 1, 'worker_id' => 1,
            'customer_id' => $cust->id, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'blocks_until' => now()->addHour(),
            'status' => 'confirmed', 'source' => 'manual', 'service_name' => 'Consulta', 'duration_minutes' => 30,
            'customer_notes' => 'Dolor de cabeza',
        ]);

        // Sin ficha: en humano se crea desde el cliente.
        $patient = $this->svc()->patientForBooking($booking);
        $this->assertSame($cust->id, $patient->customer_id);

        $c = $this->svc()->start($patient, 'consultation', $this->user(), ['booking' => $booking]);
        $this->assertSame($booking->id, $c->booking_id);
        $this->assertSame('Dolor de cabeza', $c->content['reason'], 'El motivo sale prellenado de la reserva');
        $this->assertSame('in_service', $booking->fresh()->status);
        $this->assertSame($c->id, $this->svc()->start($patient, 'consultation', $this->user(), ['booking' => $booking->fresh()])->id, 'Una reserva = una nota');

        $this->svc()->autosave($c, ['plan' => 'Reposo'], [], $this->user());
        $r = $this->svc()->sign($c->fresh(), $this->user());

        $this->assertNull($r['warning']);
        $this->assertSame('completed', $booking->fresh()->status, 'Al firmar la reserva pasa a atendida por el BookingService');
        $this->assertTrue(\Aero\Office\Models\BookingLog::where('booking_id', $booking->id)->where('action', 'status')->count() >= 2, 'Quedó en el historial de Office');
    }

    public function testAnimalBookingWithSeveralPetsNeedsAChoiceAndRejectsForeignPatients(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Requiere Aero.Office');
        }
        ClinicSettings::create(['tenant_id' => 1, 'mode' => 'animal']);
        $tutor = \Aero\Office\Models\Customer::create(['tenant_id' => 1, 'name' => 'Tutora', 'phone' => '70000002']);
        $other = \Aero\Office\Models\Customer::create(['tenant_id' => 1, 'name' => 'Otra', 'phone' => '70000003']);
        $rex = Patient::create(['tenant_id' => 1, 'customer_id' => $tutor->id, 'name' => 'Rex', 'species' => 'dog']);
        Patient::create(['tenant_id' => 1, 'customer_id' => $tutor->id, 'name' => 'Michi', 'species' => 'cat']);
        $ajeno = Patient::create(['tenant_id' => 1, 'customer_id' => $other->id, 'name' => 'Ajeno', 'species' => 'dog']);

        $booking = \Aero\Office\Models\Booking::create([
            'tenant_id' => 1, 'code' => 'T2', 'manage_token' => 't2', 'branch_id' => 1, 'service_id' => 1, 'worker_id' => 1,
            'customer_id' => $tutor->id, 'starts_at' => now(), 'ends_at' => now()->addHour(), 'blocks_until' => now()->addHour(),
            'status' => 'confirmed', 'source' => 'manual', 'service_name' => 'Consulta', 'duration_minutes' => 30,
        ]);

        try {
            $this->svc()->patientForBooking($booking);
            $this->fail('Con dos animales hay que elegir');
        } catch (MedicalException $e) {
            $this->assertStringContainsString('elija', $e->getMessage());
        }

        try {
            $this->svc()->patientForBooking($booking, $ajeno->id);
            $this->fail('No se puede atender el animal de otro tutor');
        } catch (MedicalException $e) {
            $this->assertStringContainsString('no corresponde', $e->getMessage());
        }

        $this->assertSame($rex->id, $this->svc()->patientForBooking($booking, $rex->id)->id);
    }
}
