<?php namespace Aero\Clinica\Tests;

use Aero\Clinica\Classes\ConsultationService;
use Aero\Clinica\Classes\DocumentRenderer;
use Aero\Clinica\Classes\DocumentService;
use Aero\Clinica\Classes\Exporter;
use Aero\Clinica\Classes\FileService;
use Aero\Clinica\Classes\ImportService;
use Aero\Clinica\Classes\ClinicaException;
use Aero\Clinica\Classes\Notifier;
use Aero\Clinica\Classes\PrivateFiles;
use Aero\Clinica\Classes\Reminders;
use Aero\Clinica\Classes\Scheduling;
use Aero\Clinica\Classes\VaccineService;
use Aero\Clinica\Models\ClinicSettings;
use Aero\Clinica\Models\Consultation;
use Aero\Clinica\Models\DocumentTemplate;
use Aero\Clinica\Models\LabResult;
use Aero\Clinica\Models\ClinicaDocument;
use Aero\Clinica\Models\ClinicaFile;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Vaccination;
use Illuminate\Http\UploadedFile;
use PluginTestCase;

class ComplementsTest extends PluginTestCase
{
    protected array $tmp = [];

    protected string $privDir;

    public function setUp(): void
    {
        parent::setUp();
        // Archivos cifrados en una carpeta temporal: las pruebas nunca tocan el almacenamiento real.
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
        parent::tearDown();
    }

    protected function rrmdir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    protected function user(int $id = 7, string $name = 'Ana'): object
    {
        return (object) ['id' => $id, 'first_name' => $name, 'last_name' => 'Médica', 'email' => "u{$id}@example.test"];
    }

    protected function patient(int $tenant = 1, string $mode = 'human', array $extra = []): Patient
    {
        $this->officeTables(); // Office está instalado: sus tablas deben existir en el harness
        if (!ClinicSettings::where('tenant_id', $tenant)->exists()) {
            ClinicSettings::create(['tenant_id' => $tenant]);
        }
        $p = new Patient($extra + ['tenant_id' => $tenant, 'name' => 'Paciente ' . $tenant . '-' . uniqid()]);
        if ($mode === 'linked' && $this->officeTables()) {
            $p->customer_id = \Aero\Office\Models\Customer::create(['tenant_id' => $tenant, 'name' => 'Cliente', 'phone' => '7000008' . $tenant])->id;
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
        if (!\Schema::hasTable('aero_office_customers')) {
            (require $file)->up();
        }

        return true;
    }

    // ---- archivos de verdad (cabeceras reales) ----

    protected function make(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir() . '/amtest_' . uniqid() . '_' . $name;
        file_put_contents($path, $content);
        $this->tmp[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    protected function png(): UploadedFile
    {
        return $this->make('radiografia.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEgAF/QualzQAAAABJRU5ErkJggg=='));
    }

    protected function pdf(string $text = 'resultado'): UploadedFile
    {
        return $this->make('lab.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n% {$text}\n");
    }

    protected function docx(string $text): UploadedFile
    {
        $path = sys_get_temp_dir() . '/amtest_' . uniqid() . '.docx';
        $z = new \ZipArchive();
        $z->open($path, \ZipArchive::CREATE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $z->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>' . $text . '</w:t></w:r></w:p></w:body></w:document>');
        $z->close();
        $this->tmp[] = $path;

        return new UploadedFile($path, 'historia.docx', null, null, true);
    }

    public function testMigrationsCreateTables(): void
    {
        foreach (['files', 'imports', 'vaccinations', 'document_templates', 'documents'] as $t) {
            $this->assertTrue(\Schema::hasTable('aero_clinica_' . $t), "Falta aero_clinica_{$t}");
        }
        $this->assertTrue(\Schema::hasColumn('aero_clinica_lab_results', 'file_id'));
    }

    // ---- Archivos cifrados ----

    public function testFilesAreEncryptedOnDiskWithRandomNameAndRoundTrip(): void
    {
        $f = $this->pdf('CONTENIDO-SECRETO-ZZ');
        $stored = PrivateFiles::put($f, 1);

        $this->assertSame('application/pdf', $stored['mime']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $stored['storage_name']);
        $onDisk = file_get_contents(PrivateFiles::dir() . '/1/' . substr($stored['storage_name'], 0, 2) . '/' . $stored['storage_name']);
        $this->assertStringNotContainsString('CONTENIDO-SECRETO-ZZ', $onDisk);
        $this->assertStringNotContainsString('%PDF', $onDisk);
        $this->assertStringContainsString('CONTENIDO-SECRETO-ZZ', PrivateFiles::read(1, $stored['storage_name']));
        $this->assertSame($stored['sha256'], hash('sha256', PrivateFiles::read(1, $stored['storage_name'])));

        PrivateFiles::forget(1, $stored['storage_name']);
        $this->assertFalse(is_file(PrivateFiles::dir() . '/1/' . substr($stored['storage_name'], 0, 2) . '/' . $stored['storage_name']));
    }

    public function testFileTypeIsDecidedByContentNotByExtension(): void
    {
        foreach ([
            ['x.png', '<?php echo "hola"; ?>'],            // PHP renombrado a imagen
            ['x.pdf', '<html><script>alert(1)</script></html>'],
            ['x.html', '<html></html>'],
            ['x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            ['x.docx', 'no soy un zip'],
            ['x.exe', "MZ\x90\x00"],
        ] as [$name, $body]) {
            try {
                PrivateFiles::put($this->make($name, $body), 1);
                $this->fail("{$name} debió rechazarse");
            } catch (ClinicaException $e) {
                $this->assertStringContainsString('no permitido', $e->getMessage(), $name);
            }
        }

        $this->assertSame('image/png', PrivateFiles::put($this->png(), 1)['mime']);
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', PrivateFiles::put($this->docx('hola'), 1)['mime']);
        $this->assertSame('text/plain', PrivateFiles::put($this->make('nota.txt', "texto plano\n"), 1)['mime']);
    }

    public function testEmptyAndOversizedFilesAreRejected(): void
    {
        try {
            PrivateFiles::put($this->make('vacio.pdf', ''), 1);
            $this->fail('vacío');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('vacío', $e->getMessage());
        }

        $this->expectException(ClinicaException::class);
        PrivateFiles::put($this->make('grande.pdf', "%PDF-1.4\n" . str_repeat('A', PrivateFiles::MAX_BYTES)), 1);
    }

    public function testAttachStoresFileAndNumericResultsFeedingTheSheet(): void
    {
        $p = $this->patient();
        $f = (new FileService())->attach($p, $this->pdf(), [
            'kind' => 'lab', 'title' => 'Laboratorio de control', 'taken_on' => now()->subDay()->toDateString(),
            'results' => [['analyte_name' => 'Glucosa en ayunas', 'value' => '110', 'unit' => 'mg/dL'], ['analyte_name' => '', 'value' => '']],
        ], $this->user());

        $this->assertSame('confirmed', $f->match_status);
        $this->assertSame('Laboratorio de control', ClinicaFile::find($f->id)->title);
        $this->assertStringNotContainsString('Laboratorio de control', (string) \DB::table('aero_clinica_files')->where('id', $f->id)->value('title'), 'El título va cifrado');
        $this->assertTrue($f->integrityOk());

        $lab = LabResult::where('patient_id', $p->id)->first();
        $this->assertEquals(110, $lab->value);
        $this->assertSame($f->id, (int) $lab->file_id);
        $this->assertSame(1, LabResult::where('patient_id', $p->id)->count(), 'La fila vacía no crea resultado');

        $sheet = \Aero\Clinica\Classes\FollowUpSheet::build($p->fresh());
        $this->assertContains('Glucosa en ayunas', collect($sheet['rows'])->pluck('label')->all());
    }

    public function testInvalidResultLeavesNothingBehind(): void
    {
        $p = $this->patient();
        $before = ClinicaFile::count();
        $dir = PrivateFiles::dir() . '/1';
        $filesBefore = is_dir($dir) ? count(glob($dir . '/*/*')) : 0;

        try {
            (new FileService())->attach($p, $this->pdf(), ['kind' => 'lab', 'results' => [['analyte_name' => 'Glucosa', 'value' => 'abc']]], $this->user());
            $this->fail('Resultado inválido');
        } catch (ClinicaException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertSame($before, ClinicaFile::count(), 'No queda registro a medias');
        $this->assertSame(0, LabResult::count());
        $this->assertSame($filesBefore, is_dir($dir) ? count(glob($dir . '/*/*')) : 0, 'No queda el archivo cifrado huérfano');
    }

    public function testAttachRejectsNoteOfAnotherPatient(): void
    {
        $a = $this->patient();
        $b = $this->patient();
        $note = (new ConsultationService())->start($b, 'consultation', $this->user());

        $this->expectException(ClinicaException::class);
        (new FileService())->attach($a, $this->png(), ['kind' => 'imaging', 'consultation_id' => $note->id], $this->user());
    }

    public function testFilesAreIsolatedByTenant(): void
    {
        $a = (new FileService())->attach($this->patient(1), $this->png(), ['kind' => 'imaging'], $this->user());
        (new FileService())->attach($this->patient(2), $this->png(), ['kind' => 'imaging'], $this->user());

        $this->assertSame([$a->id], ClinicaFile::forTenant(1)->pluck('id')->all());
        $this->assertCount(0, ClinicaFile::visible()->get());
    }

    // ---- Vacunación ----

    public function testManualNextDueIsKeptAndNothingIsInvented(): void
    {
        $p = $this->patient();
        $d = (new VaccineService())->apply($p, ['vaccine_name' => 'Antitetánica', 'applied_on' => now()->toDateString(), 'next_due_on' => now()->addDays(10)->toDateString()], $this->user());

        $this->assertSame(now()->addDays(10)->toDateString(), $d->next_due_on->toDateString());

        $none = (new VaccineService())->apply($p, ['vaccine_name' => 'Otra', 'applied_on' => now()->toDateString()], $this->user());
        $this->assertNull($none->next_due_on, 'Sin fecha no se inventa próxima dosis');
    }

    public function testVaccineValidationRules(): void
    {
        $p = $this->patient();
        $svc = new VaccineService();
        foreach ([
            ['vaccine_name' => ''],
            ['vaccine_name' => 'X', 'applied_on' => now()->addDay()->toDateString()],
            ['vaccine_name' => 'X', 'applied_on' => now()->toDateString(), 'next_due_on' => now()->subDays(3)->toDateString()],
            ['vaccine_name' => 'X', 'applied_on' => 'no es fecha'],
        ] as $bad) {
            try {
                $svc->apply($p, $bad, $this->user());
                $this->fail('Debió rechazar ' . json_encode($bad));
            } catch (ClinicaException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testCalendarShowsOverdueAndUpcoming(): void
    {
        $p = $this->patient(1, 'linked');

        $svc = new VaccineService();
        $svc->apply($p, ['vaccine_name' => 'Vencida', 'applied_on' => now()->subDays(40)->toDateString(), 'next_due_on' => now()->subDays(5)->toDateString()], $this->user());
        $svc->apply($p, ['vaccine_name' => 'Próxima', 'applied_on' => now()->toDateString(), 'next_due_on' => now()->addDays(5)->toDateString()], $this->user());

        $cal = $svc->calendar($p);
        $due = collect($cal['due']);
        $this->assertTrue($due->firstWhere(fn ($d) => $d['vaccination']->vaccine_name === 'Vencida')['overdue']);
        $this->assertFalse($due->firstWhere(fn ($d) => $d['vaccination']->vaccine_name === 'Próxima')['overdue']);
        $this->assertSame('Vencida', $due->first()['vaccination']->vaccine_name, 'Ordenadas por fecha');
    }

    // ---- Recordatorios ----

    public function testRemindersAreOffByDefaultAndFireOnlyIds(): void
    {
        Notifier::$muted = true;
        $p = $this->patient();
        $v = (new VaccineService())->apply($p, ['vaccine_name' => 'X', 'applied_on' => now()->subDays(30)->toDateString(), 'next_due_on' => now()->addDays(3)->toDateString()], $this->user());

        $events = [];
        \Event::listen('aero.clinica.vaccineDue', function ($payload) use (&$events) {
            $events[] = $payload;
        });

        $this->assertSame(0, Reminders::vaccines());
        $this->assertSame([], $events, 'Con los avisos apagados no pasa nada');

        ClinicSettings::where('tenant_id', 1)->update(['vaccine_reminders' => true, 'reminder_days_before' => 7]);
        Reminders::vaccines();

        $this->assertCount(1, $events);
        $this->assertSame(['vaccination_id' => $v->id, 'patient_id' => $p->id, 'tenant_id' => 1], $events[0], 'Solo ids: nada clínico hacia webhooks');
        Notifier::$muted = false;
    }

    public function testReminderIsSentOnceAndSkippedWhenAlreadyApplied(): void
    {
        $p = $this->patient();
        $svc = new VaccineService();
        ClinicSettings::where('tenant_id', 1)->update(['vaccine_reminders' => true, 'reminder_days_before' => 7]);
        $due = $svc->apply($p, ['vaccine_name' => 'Hepatitis B', 'applied_on' => now()->subDays(18)->toDateString(), 'next_due_on' => now()->addDays(3)->toDateString()], $this->user());

        $this->assertFalse(Reminders::alreadyApplied($due));
        // Se aplicó la siguiente dosis antes de que corriera el recordatorio: el aviso quedó sin objeto.
        $svc->apply($p, ['vaccine_name' => 'Hepatitis B', 'applied_on' => now()->toDateString()], $this->user());
        $this->assertTrue(Reminders::alreadyApplied($due->fresh()));
    }

    public function testNotifierNeedsAContactAndNeverThrows(): void
    {
        Notifier::$muted = false;
        $p = $this->patient();
        $v = (new VaccineService())->apply($p, ['vaccine_name' => 'X', 'applied_on' => now()->toDateString(), 'next_due_on' => now()->addDay()->toDateString()], $this->user());

        $this->assertFalse(Notifier::vaccineDue($v->fresh('patient')), 'Sin cliente con teléfono o correo no hay a quién avisar');
    }

    // ---- Documentos ----

    protected function template(string $body, string $kind = 'certificate', int $tenant = 1): DocumentTemplate
    {
        return DocumentTemplate::create(['tenant_id' => $tenant, 'name' => 'Formato ' . $kind, 'kind' => $kind, 'body' => $body, 'is_active' => true]);
    }

    public function testTemplateRejectsUnknownVariables(): void
    {
        try {
            $this->template('Certifico que {{paciente}} está sano {{typo}}');
            $this->fail('Variable desconocida');
        } catch (\ApplicationException $e) {
            $this->assertStringContainsString('{{typo}}', $e->getMessage());
        }
        $this->assertSame([], DocumentRenderer::unknownVariables('{{ paciente }} y {{fecha}}'));
    }

    public function testRendersVariablesAndLeavesBlankLinesForMissingData(): void
    {
        $p = $this->patient(1, 'human', ['name' => 'Ana Pérez', 'document' => '1234567', 'birth_date' => '1990-05-01']);
        $t = $this->template('{{paciente}}, CI {{documento}}, nacida el {{fecha_nacimiento}}. Diagnóstico: {{diagnostico}}. Firma {{profesional}} {{matricula}}.');

        $d = (new DocumentService())->generate($p, $t, $this->user());
        $this->assertStringContainsString('Ana Pérez, CI 1234567, nacida el 01/05/1990', $d->body);
        $this->assertStringContainsString('Diagnóstico: ' . DocumentRenderer::BLANK, $d->body, 'Sin diagnóstico queda una línea en blanco');
        $this->assertStringContainsString('Ana Médica', $d->body);
        $this->assertStringNotContainsString('{{', $d->body);
        $this->assertStringNotContainsString('paciente}}', $d->body);
    }

    public function testDiagnosisVariableTakesTheSignedNote(): void
    {
        $p = $this->patient();
        $svc = new ConsultationService();
        $c = $svc->start($p, 'consultation', $this->user());
        $svc->autosave($c, ['reason' => 'Dolor de garganta'], [], $this->user());
        app(\Aero\Clinica\Classes\ClinicalItems::class)->addDiagnosis($c->fresh(), ['source' => 'free', 'label' => 'Faringitis aguda'], $this->user());
        $svc->sign($c->fresh(), $this->user());

        $d = (new DocumentService())->generate($p, $this->template('Dx: {{diagnostico}}. Motivo: {{motivo}}'), $this->user());
        $this->assertSame('Dx: Faringitis aguda. Motivo: Dolor de garganta', $d->body);
    }

    public function testDocumentIssueFreezesItAndDetectsTampering(): void
    {
        $p = $this->patient(1, 'human', ['document' => '999888']);
        $svc = new DocumentService();
        $d = $svc->generate($p, $this->template('Certifico que {{paciente}} fue atendido.'), $this->user());

        $this->assertSame('draft', $d->fresh()->status);
        $svc->update($d, 'Certificado médico', 'Certifico que el paciente fue atendido el día de hoy.', $this->user());
        $issued = $svc->issue($d->fresh(), $this->user())->fresh();

        $this->assertTrue($issued->isIssued());
        $this->assertTrue($issued->integrityOk());
        $this->assertStringNotContainsString('atendido', (string) \DB::table('aero_clinica_documents')->where('id', $d->id)->value('body'), 'El texto va cifrado');

        foreach ([fn () => $issued->update(['title' => 'Otro']), fn () => $issued->delete(), fn () => $svc->update($issued, 'a', 'b', $this->user())] as $n => $attempt) {
            try {
                $attempt();
                $this->fail("Intento #{$n} sobre un documento emitido");
            } catch (\ApplicationException | ClinicaException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        \DB::table('aero_clinica_documents')->where('id', $d->id)->update(['body' => encrypt('Texto falsificado', false)]);
        $this->assertFalse(ClinicaDocument::find($d->id)->integrityOk());
    }

    public function testIssueRejectsMissingDataExceptInConsents(): void
    {
        $p = $this->patient();
        $svc = new DocumentService();

        $cert = $svc->generate($p, $this->template('{{paciente}} vive en {{matricula}}', 'certificate'), $this->user());
        try {
            $svc->issue($cert, $this->user());
            $this->fail('Un certificado con datos sin completar no se emite');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('sin completar', $e->getMessage());
        }

        $consent = $svc->generate($p, $this->template('Yo, {{paciente}}, autorizo. CI {{documento}}', 'consent'), $this->user());
        $this->assertTrue($svc->issue($consent, $this->user())->isIssued(), 'En un consentimiento la línea en blanco se completa a mano');
    }

    public function testOnlyTheAuthorEditsAndIssues(): void
    {
        $d = (new DocumentService())->generate($this->patient(), $this->template('Texto {{paciente}}'), $this->user(7));

        $this->expectException(ClinicaException::class);
        (new DocumentService())->issue($d, $this->user(8));
    }

    public function testCannotUseAnotherTenantsTemplate(): void
    {
        $t = $this->template('Texto', 'certificate', 2);

        $this->expectException(ClinicaException::class);
        (new DocumentService())->generate($this->patient(1), $t, $this->user());
    }

    // ---- Importación ----

    public function testSuggestionByDocumentByNameAndNoGuessingOnTies(): void
    {
        $svc = new ImportService();
        $ana = $this->patient(1, 'human', ['name' => 'Ana María Quispe', 'document' => '4567890']);
        $beto = $this->patient(1, 'human', ['name' => 'Beto Quispe Rojas', 'document' => '1112223']);
        $patients = Patient::where('tenant_id', 1)->get(['id', 'name', 'document']);

        [$id, $why] = $svc->suggest($patients, 'historia_4567890.pdf');
        $this->assertSame($ana->id, $id);
        $this->assertStringContainsString('documento', $why);

        [$id, $why] = $svc->suggest($patients, 'Quispe_Rojas_Beto.pdf');
        $this->assertSame($beto->id, $id);
        $this->assertStringContainsString('nombre', $why);

        [$id, $why] = $svc->suggest($patients, 'quispe.pdf');
        $this->assertNull($id, 'Un apellido compartido no basta ni se adivina');

        [$id] = $svc->suggest($patients, 'scan0001.pdf');
        $this->assertNull($id);

        [$id] = $svc->suggest($patients, 'hc_45678901.pdf');
        $this->assertNull($id, 'Un documento que solo es parte de otro número no cuenta');
    }

    public function testReceiveKeepsOriginalsUnassignedUntilAPersonConfirms(): void
    {
        $svc = new ImportService();
        $ana = $this->patient(1, 'human', ['name' => 'Ana Pérez Rojas', 'document' => '5550001']);
        $r = $svc->receive(1, [$this->docx('Historia clínica de Ana Pérez Rojas CI 5550001'), $this->png(), $this->make('malo.exe', 'MZ')], $this->user());

        $this->assertSame(2, $r['imported']);
        $this->assertCount(1, $r['errors']);
        $files = ClinicaFile::where('import_id', $r['batch']->id)->orderBy('id')->get();
        $this->assertCount(2, $files);
        foreach ($files as $f) {
            $this->assertNull($f->patient_id, 'Hasta confirmar no está en ninguna ficha');
            $this->assertTrue($f->integrityOk(), 'El original se conserva');
        }
        $this->assertSame($ana->id, (int) $files[0]->suggested_patient_id, 'El texto del Word sugiere al paciente');
        $this->assertSame('suggested', $files[0]->match_status);
        $this->assertSame('pending', $files[1]->match_status);

        $other = $this->patient(2);
        try {
            $svc->confirm($files[0], $other, $this->user());
            $this->fail('Otro negocio');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('no es de este negocio', $e->getMessage());
        }

        $svc->confirm($files[0], $ana, $this->user());
        $this->assertSame($ana->id, (int) $files[0]->fresh()->patient_id);
        $this->assertSame('confirmed', $files[0]->fresh()->match_status);

        $svc->discard($files[1], $this->user());
        $this->assertNull(ClinicaFile::find($files[1]->id));
        $this->assertTrue(ClinicaFile::withTrashed()->find($files[1]->id)->integrityOk(), 'Descartar no destruye el original');

        $this->expectException(ClinicaException::class);
        $svc->confirm($files[0]->fresh(), $ana, $this->user());
    }

    public function testImportLimitsAndEmptyBatch(): void
    {
        try {
            (new ImportService())->receive(1, [], $this->user());
            $this->fail('lote vacío');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('al menos un archivo', $e->getMessage());
        }

        $many = [];
        for ($i = 0; $i <= ImportService::MAX_FILES; $i++) {
            $many[] = $this->make("n{$i}.txt", "x{$i}");
        }
        $this->expectException(ClinicaException::class);
        (new ImportService())->receive(1, $many, $this->user());
    }

    // ---- Exportación ----

    protected function zipEntries(string $path): array
    {
        $z = new \ZipArchive();
        $z->open($path);
        $out = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $out[$z->getNameIndex($i)] = $z->getFromIndex($i);
        }
        $z->close();

        return $out;
    }

    public function testPatientExportHasEverythingDecryptedAndTheOriginalFiles(): void
    {
        $p = $this->patient(1, 'human', ['allergies' => 'Penicilina-EXPORT']);
        $svc = new ConsultationService();
        $c = $svc->start($p, 'consultation', $this->user());
        $svc->autosave($c, ['reason' => 'Motivo-EXPORT'], ['heart_rate' => '72'], $this->user());
        app(\Aero\Clinica\Classes\ClinicalItems::class)->addPrescriptionItem($c->fresh(), ['medication' => 'Medicamento-EXPORT'], $this->user());
        $svc->sign($c->fresh(), $this->user());
        (new VaccineService())->apply($p, ['vaccine_name' => 'Vacuna-EXPORT', 'applied_on' => now()->toDateString()], $this->user());
        (new FileService())->attach($p, $this->pdf('ORIGINAL-EXPORT'), ['kind' => 'lab', 'title' => 'Estudio'], $this->user());

        $path = Exporter::patient($p->fresh());
        $this->tmp[] = $path;
        $zip = $this->zipEntries($path);

        $this->assertArrayHasKey('paciente.json', $zip);
        $json = json_decode($zip['paciente.json'], true);
        $this->assertSame('Penicilina-EXPORT', $json['paciente']['allergies']);
        $this->assertSame('Motivo-EXPORT', $json['notas'][0]['contenido']['reason']);
        $this->assertTrue($json['notas'][0]['integra']);
        $this->assertSame('Medicamento-EXPORT', $json['notas'][0]['receta']['medicamentos'][0]['medication']);
        $this->assertSame('Vacuna-EXPORT', $json['vacunas'][0]['vaccine_name']);
        $fileEntries = array_filter(array_keys($zip), fn ($k) => str_starts_with($k, 'archivos/'));
        $this->assertCount(1, $fileEntries);
        $this->assertStringContainsString('ORIGINAL-EXPORT', $zip[array_values($fileEntries)[0]], 'El archivo original va descifrado');
        $this->assertArrayHasKey('LEEME.txt', $zip);
    }

    public function testBusinessExportCoversAllPatientsAndNeverAnotherTenant(): void
    {
        $a1 = $this->patient(1, 'human', ['name' => 'Paciente Uno']);
        $a2 = $this->patient(1, 'human', ['name' => 'Paciente Dos']);
        $other = $this->patient(2, 'human', ['name' => 'Paciente Ajeno', 'allergies' => 'SECRETO-DE-OTRO-NEGOCIO']);
        $this->template('Texto {{paciente}}');
        $f = (new ImportService())->receive(1, [$this->pdf('SIN-ASOCIAR')], $this->user());

        $path = Exporter::business(1);
        $this->tmp[] = $path;
        $zip = $this->zipEntries($path);

        $names = array_column(json_decode($zip['pacientes.json'], true), 'nombre');
        $this->assertEqualsCanonicalizing(['Paciente Uno', 'Paciente Dos'], $names);
        $this->assertArrayHasKey('negocio.json', $zip);
        $this->assertArrayHasKey('bitacora.json', $zip);
        $this->assertNotEmpty(json_decode($zip['negocio.json'], true)['formatos_de_documento']);
        $all = implode("\n", $zip);
        $this->assertStringNotContainsString('SECRETO-DE-OTRO-NEGOCIO', $all);
        $this->assertStringNotContainsString('Paciente Ajeno', $all);
        $this->assertCount(1, array_filter(array_keys($zip), fn ($k) => str_starts_with($k, 'sin-asociar/')), 'Lo importado sin asociar también es del negocio');
    }

    // ---- Agendar un control con Office ----

    protected function officeBusiness(int $tenant = 1): array
    {
        $this->officeTables();
        $hours = [];
        foreach (range(1, 7) as $d) {
            $hours[] = ['weekday' => $d, 'start_time' => '09:00', 'end_time' => '12:00'];
        }
        $branch = \Aero\Office\Models\Branch::create(['tenant_id' => $tenant, 'name' => 'Central', 'hours' => $hours, 'is_active' => true]);
        $service = \Aero\Office\Models\Service::create(['tenant_id' => $tenant, 'name' => 'Control', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'price' => 0, 'is_active' => true, 'is_public' => true]);
        $worker = \Aero\Office\Models\Worker::create(['tenant_id' => $tenant, 'name' => 'Dra. Ana', 'is_active' => true, 'is_public' => true]);
        $branch->services()->attach($service->id);
        $branch->workers()->attach($worker->id);
        $service->workers()->attach($worker->id);
        \Aero\Office\Models\OfficeSettings::create(['tenant_id' => $tenant, 'enabled' => true, 'public_enabled' => true, 'min_notice_hours' => 0, 'max_advance_days' => 90, 'slot_step_minutes' => 30]);

        return [$branch, $service, $worker];
    }

    public function testBookControlUsesTheBookingServiceAndRespectsAvailability(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Requiere Aero.Office');
        }
        \Aero\Office\Classes\Notifier::$muted = true;
        [$branch, $service, $worker] = $this->officeBusiness();
        $p = $this->patient(1, 'linked');
        $when = now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i');

        $b = Scheduling::bookControl($p, ['branch_id' => $branch->id, 'service_id' => $service->id, 'starts_at' => $when, 'reason' => 'Refuerzo de vacuna'], $this->user());

        $this->assertSame('confirmed', $b->status);
        $this->assertSame($p->customer_id, (int) $b->customer_id, 'La reserva es del cliente vinculado');
        $this->assertSame('Refuerzo de vacuna', $b->customer_notes, 'El motivo viaja a la reserva y prellenará la nota');
        $this->assertSame($worker->id, (int) $b->worker_id);
        $this->assertTrue(\Aero\Office\Models\BookingLog::where('booking_id', $b->id)->where('action', 'created')->exists(), 'Queda en el historial de Office');

        // Mismo horario: Office lo rechaza y no se escribe nada a mano.
        try {
            Scheduling::bookControl($this->patient(1, 'linked'), ['branch_id' => $branch->id, 'service_id' => $service->id, 'worker_id' => $worker->id, 'starts_at' => $when], $this->user());
            $this->fail('Horario ocupado');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('ocupa', $e->getMessage());
        }
        $this->assertSame(1, \Aero\Office\Models\Booking::count());
    }

    public function testBookControlNeedsALinkedCustomerAndRejectsForeignBranch(): void
    {
        if (!$this->officeTables()) {
            $this->markTestSkipped('Requiere Aero.Office');
        }
        \Aero\Office\Classes\Notifier::$muted = true;
        [$branch, $service] = $this->officeBusiness(1);
        [$otherBranch, $otherService] = $this->officeBusiness(2);

        $loose = $this->patient(1, 'human');
        try {
            Scheduling::bookControl($loose, ['branch_id' => $branch->id, 'service_id' => $service->id, 'starts_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString()], $this->user());
            $this->fail('Sin cliente vinculado');
        } catch (ClinicaException $e) {
            $this->assertStringContainsString('vinculado', $e->getMessage());
        }

        $p = $this->patient(1, 'linked');
        $this->expectException(ClinicaException::class);
        Scheduling::bookControl($p, ['branch_id' => $otherBranch->id, 'service_id' => $otherService->id, 'starts_at' => now()->addDays(2)->setTime(9, 0)->toDateTimeString()], $this->user());
    }
}
