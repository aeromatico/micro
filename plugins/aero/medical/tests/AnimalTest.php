<?php namespace Aero\Medical\Tests;

use Aero\Medical\Classes\DewormingService;
use Aero\Medical\Classes\Exporter;
use Aero\Medical\Classes\FileAccess;
use Aero\Medical\Classes\GroomingService;
use Aero\Medical\Classes\MedicalException;
use Aero\Medical\Classes\Notifier;
use Aero\Medical\Classes\PrivateFiles;
use Aero\Medical\Classes\Reminders;
use Aero\Medical\Models\ClinicSettings;
use Aero\Medical\Models\Deworming;
use Aero\Medical\Models\GroomingVisit;
use Aero\Medical\Models\MedicalFile;
use Aero\Medical\Models\Patient;
use Illuminate\Http\UploadedFile;
use PluginTestCase;

class AnimalTest extends PluginTestCase
{
    protected array $tmp = [];

    protected string $privDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->privDir = sys_get_temp_dir() . '/amtest-priv-' . bin2hex(random_bytes(6));
        PrivateFiles::$dirOverride = $this->privDir;
    }

    public function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        PrivateFiles::$dirOverride = null;
        $this->rrmdir($this->privDir);
        Notifier::$muted = false;
        parent::tearDown();
    }

    protected function rrmdir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    protected function user(int $id = 7): object
    {
        return (object) ['id' => $id, 'first_name' => 'Vet', 'last_name' => 'Prueba', 'email' => "u{$id}@example.test"];
    }

    protected function officeTables(): bool
    {
        $file = base_path('plugins/aero/office/updates/create_office_tables.php');
        if (!is_file($file)) {
            return false;
        }
        if (!\Schema::hasTable('aero_office_customers')) {
            (require $file)->up();
        }

        return true;
    }

    protected function pet(int $tenant = 1, string $mode = 'animal'): Patient
    {
        $this->officeTables();
        if (!ClinicSettings::where('tenant_id', $tenant)->exists()) {
            ClinicSettings::create(['tenant_id' => $tenant, 'mode' => $mode]);
        }
        $p = new Patient(['tenant_id' => $tenant, 'name' => 'Pet ' . uniqid(), 'species' => $mode === 'animal' ? 'dog' : null]);
        $p->kind = $mode;
        if ($mode === 'animal') {
            $p->customer_id = \Aero\Office\Models\Customer::create(['tenant_id' => $tenant, 'name' => 'Tutor', 'phone' => '7000007' . $tenant])->id;
        }
        $p->save();

        return $p;
    }

    protected function file(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir() . '/amtest_' . uniqid() . '_' . $name;
        file_put_contents($path, $content);
        $this->tmp[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    protected function png(): UploadedFile
    {
        return $this->file('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEgAF/QualzQAAAABJRU5ErkJggg=='));
    }

    /** SQLite no hace cumplir el largo de las columnas y MySQL estricto sí: los códigos de tipo deben caber. */
    public function testFileKindCodesFitTheColumn(): void
    {
        foreach (array_keys(MedicalFile::KINDS) as $kind) {
            $this->assertLessThanOrEqual(24, strlen($kind), "El tipo «{$kind}» no cabe en aero_medical_files.kind (24)");
        }
        foreach (['pending', 'suggested', 'confirmed', 'discarded'] as $status) {
            $this->assertLessThanOrEqual(12, strlen($status));
        }
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['dewormings', 'grooming_visits'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_medical_' . $t), "Falta aero_medical_{$t}");
        }
        $this->assertTrue(\Schema::hasColumn('aero_medical_files', 'grooming_id'));
        $this->assertTrue(\Schema::hasColumn('aero_medical_settings', 'deworming_reminders'));
    }

    // ---- Solo animal ----

    public function testModulesAreAnimalOnlyAndTheServerEnforcesIt(): void
    {
        $human = $this->pet(1, 'human');

        foreach ([
            fn () => (new DewormingService())->apply($human, ['product' => 'X'], $this->user()),
            fn () => (new GroomingService())->create($human, ['service' => 'Baño'], $this->user()),
        ] as $n => $attempt) {
            try {
                $attempt();
                $this->fail("Intento #{$n}: un paciente humano no puede usar el módulo animal");
            } catch (MedicalException $e) {
                $this->assertStringContainsString('animales', $e->getMessage());
            }
        }
        $this->assertSame(0, Deworming::count() + GroomingVisit::count());
    }

    // ---- Desparasitaciones ----

    public function testDewormingNextDueOnlyFromTheBusinessInterval(): void
    {
        $p = $this->pet();
        $svc = new DewormingService();

        $none = $svc->apply($p, ['product' => 'Antiparasitario A', 'dose' => '1 comp.', 'applied_on' => now()->toDateString()], $this->user());
        $this->assertNull($none->next_due_on, 'Sin intervalo del negocio no se inventa próxima fecha');

        ClinicSettings::where('tenant_id', 1)->update(['deworming_interval_days' => 90]);
        $auto = $svc->apply($p, ['product' => 'Antiparasitario A', 'applied_on' => now()->toDateString()], $this->user());
        $this->assertSame(now()->addDays(90)->toDateString(), $auto->next_due_on->toDateString());

        $manual = $svc->apply($p, ['product' => 'B', 'applied_on' => now()->toDateString(), 'next_due_on' => now()->addDays(15)->toDateString(), 'kind' => 'external'], $this->user());
        $this->assertSame(now()->addDays(15)->toDateString(), $manual->next_due_on->toDateString(), 'Lo indicado a mano manda');
        $this->assertSame('Externa', $manual->kind_label);
    }

    public function testDewormingValidation(): void
    {
        $p = $this->pet();
        $svc = new DewormingService();
        foreach ([
            ['product' => ''],
            ['product' => 'X', 'applied_on' => now()->addDay()->toDateString()],
            ['product' => 'X', 'applied_on' => now()->toDateString(), 'next_due_on' => now()->subDays(2)->toDateString()],
            ['product' => 'X', 'applied_on' => 'nunca'],
        ] as $bad) {
            try {
                $svc->apply($p, $bad, $this->user());
                $this->fail('Debió rechazar ' . json_encode($bad));
            } catch (MedicalException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertNull(Deworming::first()?->kind);
    }

    public function testDewormingCalendarKeepsOnlyTheLastApplicationPerProduct(): void
    {
        $p = $this->pet();
        $svc = new DewormingService();
        $svc->apply($p, ['product' => 'Producto A', 'applied_on' => now()->subDays(100)->toDateString(), 'next_due_on' => now()->subDays(10)->toDateString()], $this->user());
        $svc->apply($p, ['product' => 'Producto A', 'applied_on' => now()->subDays(5)->toDateString(), 'next_due_on' => now()->addDays(85)->toDateString()], $this->user());
        $svc->apply($p, ['product' => 'Producto B', 'applied_on' => now()->subDays(60)->toDateString(), 'next_due_on' => now()->subDays(3)->toDateString()], $this->user());

        $due = collect($svc->calendar($p)['due']);
        $this->assertCount(2, $due, 'La dosis vieja de A ya no cuenta: hubo una posterior');
        $this->assertSame('Producto B', $due->first()['deworming']->product, 'Ordenadas por fecha');
        $this->assertTrue($due->first()['overdue']);
        $this->assertFalse($due->last()['overdue']);
    }

    public function testDewormingRemindersAreOffByDefaultAndCarryOnlyIds(): void
    {
        Notifier::$muted = true;
        $p = $this->pet();
        $d = (new DewormingService())->apply($p, ['product' => 'X', 'applied_on' => now()->subDays(80)->toDateString(), 'next_due_on' => now()->addDays(3)->toDateString()], $this->user());

        $events = [];
        \Event::listen('aero.medical.dewormingDue', function ($payload) use (&$events) {
            $events[] = $payload;
        });

        Reminders::dewormings();
        $this->assertSame([], $events, 'Apagado por defecto');

        ClinicSettings::where('tenant_id', 1)->update(['deworming_reminders' => true, 'reminder_days_before' => 7]);
        Reminders::dewormings();
        $this->assertCount(1, $events);
        $this->assertSame(['deworming_id' => $d->id, 'patient_id' => $p->id, 'tenant_id' => 1], $events[0], 'Solo ids hacia webhooks');
    }

    public function testDewormingReminderSkippedWhenAlreadyDone(): void
    {
        $p = $this->pet();
        $svc = new DewormingService();
        $old = $svc->apply($p, ['product' => 'Z', 'applied_on' => now()->subDays(30)->toDateString(), 'next_due_on' => now()->addDays(2)->toDateString()], $this->user());
        $this->assertFalse(Reminders::dewormingDone($old));

        $svc->apply($p, ['product' => 'Z', 'applied_on' => now()->toDateString()], $this->user());
        $this->assertTrue(Reminders::dewormingDone($old->fresh()));
    }

    public function testDewormingNotifierNeedsContactAndNeverThrows(): void
    {
        $p = new Patient(['tenant_id' => 1, 'name' => 'Sin tutor']);
        $p->kind = 'animal';
        $d = new Deworming(['tenant_id' => 1]);
        $d->setRelation('patient', $p);
        $d->next_due_on = now()->addDay();

        $this->assertFalse(Notifier::dewormingDue($d));
    }

    // ---- Peluquería y spa ----

    public function testGroomingVisitCreationAndValidation(): void
    {
        $p = $this->pet();
        $svc = new GroomingService();
        $v = $svc->create($p, ['service' => 'Baño y corte', 'visited_on' => now()->toDateString(), 'notes' => 'Muy inquieto', 'next_visit_on' => now()->addDays(30)->toDateString()], $this->user());

        $this->assertSame('Baño y corte', $v->service);
        $this->assertSame('Muy inquieto', GroomingVisit::find($v->id)->notes);
        $this->assertStringNotContainsString('inquieto', (string) \DB::table('aero_medical_grooming_visits')->where('id', $v->id)->value('notes'), 'Las notas van cifradas');

        foreach ([['service' => ''], ['service' => 'X', 'visited_on' => now()->addDay()->toDateString()], ['service' => 'X', 'visited_on' => now()->toDateString(), 'next_visit_on' => now()->subDay()->toDateString()]] as $bad) {
            try {
                $svc->create($p, $bad, $this->user());
                $this->fail('Debió rechazar ' . json_encode($bad));
            } catch (MedicalException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testBeforeAfterPhotosAreEncryptedImagesOnly(): void
    {
        $v = (new GroomingService())->create($this->pet(), ['service' => 'Spa'], $this->user());
        $svc = new GroomingService();

        $f = $svc->addPhoto($v, 'before', $this->png(), $this->user());
        $this->assertSame('grooming_before', $f->kind);
        $this->assertSame($v->id, (int) $f->grooming_id);
        $this->assertTrue($f->integrityOk());
        $raw = file_get_contents($this->privDir . '/1/' . substr($f->storage_name, 0, 2) . '/' . $f->storage_name);
        $this->assertStringNotContainsString('PNG', $raw, 'La foto va cifrada en disco');
        $svc->addPhoto($v, 'after', $this->png(), $this->user());
        $this->assertCount(1, $v->photosOf('before'));
        $this->assertCount(1, $v->photosOf('after'));

        // Un PDF no es una foto y no deja un archivo cifrado huérfano.
        $count = count(glob($this->privDir . '/1/*/*'));
        try {
            $svc->addPhoto($v, 'after', $this->file('doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF\n"), $this->user());
            $this->fail('Solo imágenes');
        } catch (MedicalException $e) {
            $this->assertStringContainsString('imágenes', $e->getMessage());
        }
        $this->assertSame($count, count(glob($this->privDir . '/1/*/*')));

        $this->expectException(MedicalException::class);
        $svc->addPhoto($v, 'durante', $this->png(), $this->user());
    }

    public function testAFailedDatabaseWriteLeavesNoOrphanEncryptedFile(): void
    {
        $v = (new GroomingService())->create($this->pet(), ['service' => 'Baño'], $this->user());

        MedicalFile::creating(function () {
            throw new \RuntimeException('falla simulada de la base de datos');
        });

        try {
            (new GroomingService())->addPhoto($v, 'before', $this->png(), $this->user());
            $this->fail('Debió propagar el error');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('simulada', $e->getMessage());
        }
        $this->assertSame([], glob($this->privDir . '/*/*/*') ?: [], 'No debe quedar el archivo cifrado sin registro');

        $r = (new \Aero\Medical\Classes\ImportService())->receive(1, [$this->png()], $this->user());
        $this->assertSame(0, $r['imported']);
        $this->assertCount(1, $r['errors']);
        $this->assertSame([], glob($this->privDir . '/*/*/*') ?: [], 'La importación tampoco deja huérfanos');
    }

    public function testPhotoLimitPerSide(): void
    {
        $v = (new GroomingService())->create($this->pet(), ['service' => 'Baño'], $this->user());
        $svc = new GroomingService();
        for ($i = 0; $i < GroomingService::MAX_PHOTOS_PER_SIDE; $i++) {
            $svc->addPhoto($v, 'before', $this->png(), $this->user());
        }

        $this->expectException(MedicalException::class);
        $svc->addPhoto($v, 'before', $this->png(), $this->user());
    }

    public function testDeletingAVisitRemovesItsPhotosButNotAnotherVisitsPhotos(): void
    {
        $p = $this->pet();
        $svc = new GroomingService();
        $a = $svc->create($p, ['service' => 'A'], $this->user());
        $b = $svc->create($p, ['service' => 'B'], $this->user());
        $fa = $svc->addPhoto($a, 'before', $this->png(), $this->user());
        $fb = $svc->addPhoto($b, 'before', $this->png(), $this->user());

        try {
            $svc->removePhoto($a, $fb->id);
            $this->fail('La foto es de otra visita');
        } catch (MedicalException $e) {
            $this->assertStringContainsString('no encontrada', $e->getMessage());
        }

        $svc->delete($a);
        $this->assertNull(GroomingVisit::find($a->id));
        $this->assertNull(MedicalFile::find($fa->id), 'La foto se va con su visita');
        $this->assertNotNull(MedicalFile::find($fb->id));
        $this->assertTrue(MedicalFile::withTrashed()->find($fa->id)->integrityOk(), 'Eliminar es lógico: el original cifrado se conserva');
    }

    public function testGroomingIsolatedByTenant(): void
    {
        $svc = new GroomingService();
        $a = $svc->create($this->pet(1), ['service' => 'A'], $this->user());
        $svc->create($this->pet(2), ['service' => 'B'], $this->user());

        $this->assertSame([$a->id], GroomingVisit::forTenant(1)->pluck('id')->all());
        $this->assertCount(0, GroomingVisit::visible()->get());
    }

    public function testBookNextVisitUsesOfficeAndLinksTheBooking(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Requiere Aero.Office');
        }
        \Aero\Office\Classes\Notifier::$muted = true;
        $hours = array_map(fn ($d) => ['weekday' => $d, 'start_time' => '09:00', 'end_time' => '12:00'], range(1, 7));
        $branch = \Aero\Office\Models\Branch::create(['tenant_id' => 1, 'name' => 'Central', 'hours' => $hours, 'is_active' => true]);
        $service = \Aero\Office\Models\Service::create(['tenant_id' => 1, 'name' => 'Spa', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'price' => 0, 'is_active' => true, 'is_public' => true]);
        $worker = \Aero\Office\Models\Worker::create(['tenant_id' => 1, 'name' => 'Groomer', 'is_active' => true, 'is_public' => true]);
        $branch->services()->attach($service->id);
        $branch->workers()->attach($worker->id);
        $service->workers()->attach($worker->id);
        \Aero\Office\Models\OfficeSettings::create(['tenant_id' => 1, 'enabled' => true, 'public_enabled' => true, 'min_notice_hours' => 0, 'max_advance_days' => 90, 'slot_step_minutes' => 30]);

        $v = (new GroomingService())->create($this->pet(), ['service' => 'Baño'], $this->user());
        $b = (new GroomingService())->bookNext($v->fresh('patient'), ['branch_id' => $branch->id, 'service_id' => $service->id, 'starts_at' => now()->addDays(14)->setTime(10, 0)->format('Y-m-d\TH:i')], $this->user());

        $this->assertSame($b->id, (int) $v->fresh()->booking_id);
        $this->assertSame($b->starts_at->toDateString(), $v->fresh()->next_visit_on->toDateString());
        $this->assertSame('Peluquería y spa: Baño', $b->customer_notes);
        $this->assertSame('confirmed', $b->status);
    }

    // ---- Quién lee las fotos ----

    protected function who(array $perms): object
    {
        return new class($perms) {
            public function __construct(public array $perms)
            {
            }

            public function hasAccess($p, $all = true): bool
            {
                $p = (array) $p;
                $have = array_intersect($p, $this->perms);

                return $all ? count($have) === count($p) : count($have) > 0;
            }
        };
    }

    public function testFileAccessSeparatesTheClinicalRecordFromGroomingPhotos(): void
    {
        $pro = $this->who(['aero.medical.professional']);
        $reception = $this->who(['aero.medical.reception']);
        $admin = $this->who(['aero.medical.use', 'aero.medical.settings', 'aero.medical.reception']);
        $nobody = $this->who([]);

        $photo = new MedicalFile(['kind' => 'grooming_before']);
        $lab = new MedicalFile(['kind' => 'lab']);
        $history = new MedicalFile(['kind' => 'history']);

        foreach ([$photo, $lab, $history] as $f) {
            $this->assertTrue(FileAccess::canRead($pro, $f), 'El personal clínico lee todo');
            $this->assertFalse(FileAccess::canRead($nobody, $f));
        }
        $this->assertTrue(FileAccess::canRead($reception, $photo), 'Recepción sube las fotos de peluquería, así que las ve');
        $this->assertTrue(FileAccess::canRead($admin, $photo));
        $this->assertFalse(FileAccess::canRead($reception, $lab), 'Recepción NO lee el expediente clínico');
        $this->assertFalse(FileAccess::canRead($reception, $history));
        $this->assertFalse(FileAccess::canRead($admin, $lab));
        $this->assertFalse(FileAccess::canRead(null, $photo));
    }

    public function testGroomingPhotosDoNotAppearAmongClinicalFiles(): void
    {
        $v = (new GroomingService())->create($this->pet(), ['service' => 'Baño'], $this->user());
        (new GroomingService())->addPhoto($v, 'before', $this->png(), $this->user());

        $this->assertSame(0, MedicalFile::where('patient_id', $v->patient_id)->whereIn('kind', MedicalFile::CLINICAL_KINDS)->count());
        $this->assertSame(1, MedicalFile::where('patient_id', $v->patient_id)->count());
    }

    // ---- Exportación ----

    public function testPatientExportIncludesDewormingsGroomingAndPhotos(): void
    {
        $p = $this->pet();
        (new DewormingService())->apply($p, ['product' => 'Producto-EXPORT', 'applied_on' => now()->toDateString()], $this->user());
        $v = (new GroomingService())->create($p, ['service' => 'Servicio-EXPORT', 'notes' => 'Nota-EXPORT'], $this->user());
        (new GroomingService())->addPhoto($v, 'after', $this->png(), $this->user());

        $path = Exporter::patient($p->fresh());
        $this->tmp[] = $path;
        $z = new \ZipArchive();
        $z->open($path);
        $json = json_decode($z->getFromName('paciente.json'), true);
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = $z->getNameIndex($i);
        }
        $z->close();

        $this->assertSame('Producto-EXPORT', $json['desparasitaciones'][0]['product']);
        $this->assertSame('Servicio-EXPORT', $json['peluqueria'][0]['service']);
        $this->assertSame('Nota-EXPORT', $json['peluqueria'][0]['notes']);
        $this->assertSame($v->id, $json['archivos'][0]['visita_peluqueria']);
        $this->assertCount(1, array_filter($names, fn ($n) => str_starts_with($n, 'archivos/')), 'La foto original va en la exportación');
    }
}
