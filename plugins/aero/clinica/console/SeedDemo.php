<?php namespace Aero\Clinica\Console;

use Aero\Clinica\Classes\ClinicalItems;
use Aero\Clinica\Classes\ConsultationService;
use Aero\Clinica\Classes\DefaultDocuments;
use Aero\Clinica\Classes\DocumentService;
use Aero\Clinica\Classes\FileService;
use Aero\Clinica\Classes\VaccineService;
use Aero\Clinica\Models\Analyte;
use Aero\Clinica\Models\Charge;
use Aero\Clinica\Models\Cie10;
use Aero\Clinica\Models\ClinicSettings;
use Aero\Clinica\Models\Consultation;
use Aero\Clinica\Models\DiagnosisCatalog;
use Aero\Clinica\Models\DocumentTemplate;
use Aero\Clinica\Models\ExamCatalog;
use Aero\Clinica\Models\NoteTemplate;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Professional;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Datos 100 % ficticios de PEDIATRÍA (pacientes menores de 18 años) para la demostración. Cada paciente lleva el
 * documento «DEMO-n»: con eso --purge los borra sin tocar nada más. No crea clientes de Office salvo los responsables
 * de las reservas de ejemplo (nota «DEMO-AGENDA»), no activa recordatorios y no usa teléfonos ni correos.
 * No carga rangos de referencia: los de adultos engañarían en niños, y en laboratorio manda la referencia del laboratorio.
 */
class SeedDemo extends Command
{
    protected $signature = 'clinica:seed-demo {tenant=25 : Tenant (el maestro por defecto)} {--purge : Borra lo sembrado (documentos DEMO-*)} {--force : Permite otro tenant}';

    protected $description = 'Siembra o borra pacientes pediátricos ficticios de demostración';

    protected function doctor(): object
    {
        return (object) ['id' => 900001, 'first_name' => 'Valeria', 'last_name' => 'Montaño (demo)', 'email' => 'demo@market.invalid'];
    }

    public function handle(): int
    {
        $tenant = (int) $this->argument('tenant');
        if ($tenant !== 25 && !$this->option('force')) {
            $this->error('La demo solo va en el tenant maestro (25). Use --force para otro.');

            return 1;
        }

        if ($this->option('purge')) {
            return $this->purge($tenant);
        }
        if (Patient::where('tenant_id', $tenant)->where('document', 'like', 'DEMO-%')->exists()) {
            $this->error('La demo ya está sembrada. Use --purge primero si quiere rehacerla.');

            return 1;
        }
        if (!Cie10::exists()) {
            $this->error('Falta el catálogo CIE-10: php artisan clinica:import-cie10');

            return 1;
        }

        ClinicSettings::firstOrCreate(['tenant_id' => $tenant]); // recordatorios apagados
        NoteTemplate::ensureDefaults($tenant);
        DefaultDocuments::load($tenant);
        Analyte::ensureDefaults($tenant);
        $this->pediatricSetup($tenant);

        $u = $this->doctor();
        $prof = Professional::forUser($tenant, $u);
        $prof->forceFill(['title' => 'Dra.', 'specialty' => 'Pediatría', 'license' => 'DEMO-0000'])->saveQuietly();

        $this->sofia($tenant, $u);
        $this->mateo($tenant, $u);
        $this->lucia($tenant, $u);
        $this->thiago($tenant, $u);
        $this->valentina($tenant, $u);
        $this->camila($tenant, $u);
        $this->diego($tenant, $u);
        $this->joaquin($tenant, $u);
        $agenda = $this->agenda($tenant);

        $this->info('Demo pediátrica sembrada: ' . Patient::where('tenant_id', $tenant)->where('document', 'like', 'DEMO-%')->count() . ' pacientes ficticios' . ($agenda ? ' y ' . $agenda . ' reservas de ejemplo' : '') . '.');

        return 0;
    }

    /** Plantillas propias de pediatría, formatos para responsables y catálogos cortos. Todo editable por la clínica. */
    protected function pediatricSetup(int $tenant): void
    {
        $text = fn (string $key, string $label, int $rows = 3) => ['key' => $key, 'label' => $label, 'type' => 'text', 'rows' => $rows];
        $vitals = ['key' => 'vitals', 'label' => 'Signos vitales, peso y talla', 'type' => 'vitals', 'rows' => 0];

        $consulta = NoteTemplate::firstOrCreate(['tenant_id' => $tenant, 'kind' => 'consultation', 'name' => 'Consulta pediátrica'], ['is_active' => true, 'sections' => [
            $text('reason', 'Motivo de consulta (relato del niño y de los padres)', 2),
            $text('current_illness', 'Enfermedad actual', 4),
            $text('background', 'Antecedentes relevantes (perinatales, alimentación, escolaridad)', 3),
            $text('systems_review', 'Revisión por sistemas', 3),
            $vitals,
            $text('physical_exam', 'Examen físico', 4),
            $text('impression', 'Impresión diagnóstica', 3),
            $text('treatment', 'Tratamiento', 3),
            $text('alarm_signs', 'Signos de alarma explicados a los padres', 2),
            $text('plan', 'Plan y próximo control', 3),
        ]]);
        $control = NoteTemplate::firstOrCreate(['tenant_id' => $tenant, 'kind' => 'control', 'name' => 'Control de niño sano'], ['is_active' => true, 'sections' => [
            $text('reason', 'Motivo del control', 2),
            $text('feeding', 'Alimentación y lactancia', 3),
            $text('development', 'Desarrollo psicomotor y escolaridad', 3),
            $vitals,
            $text('physical_exam', 'Examen físico', 4),
            $text('vaccines_review', 'Revisión del carnet de vacunas', 2),
            $text('guidance', 'Indicaciones a los padres o responsable', 3),
            $text('plan', 'Plan y próximo control', 3),
        ]]);
        ClinicSettings::where('tenant_id', $tenant)->update(['consultation_template_id' => $consulta->id, 'control_template_id' => $control->id]);

        $formats = [
            ['certificate', 'Certificado médico escolar', "CERTIFICADO MÉDICO ESCOLAR\n\nYo, {{profesional}}, {{especialidad}}, matrícula {{matricula}}, certifico que el/la menor {{paciente}}, de {{edad}} de edad, fue evaluado/a el {{fecha}}.\n\nDiagnóstico: {{diagnostico}}\n\nSe indica reposo con ausencia escolar por __________ días, a partir de la fecha. Responsable: {{contacto}}.\n\n{{negocio}}, {{fecha}}"],
            ['consent', 'Consentimiento de vacunación (responsable)', "CONSENTIMIENTO PARA VACUNACIÓN\n\nYo, {{contacto}}, responsable del/la menor {{paciente}} ({{edad}}), declaro que el/la profesional {{profesional}} me explicó la vacuna a aplicar: __________, sus beneficios, las reacciones posibles y las alternativas, y que pude hacer preguntas.\n\nAutorizo su aplicación.\n\nLugar y fecha: {{negocio}}, {{fecha}}"],
        ];
        foreach ($formats as [$kind, $name, $body]) {
            DocumentTemplate::firstOrCreate(['tenant_id' => $tenant, 'name' => $name], ['kind' => $kind, 'body' => $body, 'is_active' => true]);
        }

        foreach (['Hemograma completo' => 'lab', 'Hemoglobina' => 'lab', 'Examen general de orina' => 'lab', 'Coproparasitológico' => 'lab',
                  'Radiografía de tórax' => 'imaging', 'Ecografía abdominal' => 'imaging', 'Audiometría' => 'other'] as $n => $cat) {
            ExamCatalog::firstOrCreate(['tenant_id' => $tenant, 'name' => $n], ['category' => $cat, 'is_active' => true]);
        }
        foreach ([['Resfrío común', 'J00'], ['Faringoamigdalitis aguda', 'J03.9'], ['Control de niño sano', 'Z00.1']] as [$l, $code]) {
            DiagnosisCatalog::firstOrCreate(['tenant_id' => $tenant, 'source' => 'custom', 'entry_key' => $code], ['code' => $code, 'label' => $l, 'pinned' => false]);
        }
    }

    /** Crea el paciente y lo marca con DEMO-n por consulta directa (así no se crea un cliente de Office). */
    protected function patient(int $tenant, int $n, array $data): Patient
    {
        $p = Patient::create($data + ['tenant_id' => $tenant]);
        DB::table('aero_clinica_patients')->where('id', $p->id)->update(['document' => 'DEMO-' . $n]);

        return $p->fresh();
    }

    /** Abre, llena y firma una nota; después la fecha en el pasado (la fecha no entra en la huella). */
    protected function note(Patient $p, string $kind, int $daysAgo, array $content, array $vitals, ?callable $extra = null)
    {
        $svc = new ConsultationService();
        $u = $this->doctor();
        $c = $svc->start($p, $kind, $u);
        $svc->autosave($c, $content, $vitals, $u);
        if ($extra) {
            $extra($c->fresh(), new ClinicalItems(), $u);
        }
        $svc->sign($c->fresh(), $u);

        $at = now()->subDays($daysAgo)->setTime(10, 30);
        DB::table('aero_clinica_consultations')->where('id', $c->id)->update(['consulted_at' => $at, 'signed_at' => $at]);
        DB::table('aero_clinica_vitals')->where('consultation_id', $c->id)->update(['measured_at' => $at]);

        // La fecha entra en la huella: se recalcula para que la nota sembrada verifique íntegra.
        $fresh = Consultation::with('vitals')->find($c->id);
        DB::table('aero_clinica_consultations')->where('id', $c->id)->update(['content_hash' => $fresh->computeHash()]);

        return $fresh->fresh();
    }

    protected function lab(Patient $p, string $name, string $value, string $unit, int $daysAgo, ?string $lo = null, ?string $hi = null, string $lab = 'Laboratorio Central (demo)'): void
    {
        (new ClinicalItems())->addLab($p, [
            'analyte_name' => $name, 'value' => $value, 'unit' => $unit, 'measured_on' => now()->subDays($daysAgo)->toDateString(),
            'ref_low' => $lo, 'ref_high' => $hi, 'lab_name' => $lab,
        ], $this->doctor());
    }

    /** Cobro de demostración: se crea sin pasar por el servicio, así no genera asientos en Finanzas ni QR. */
    protected function charge(Patient $p, $note, string $concept, string $amount, string $method, int $daysAgo): void
    {
        $c = new Charge(['tenant_id' => $p->tenant_id, 'patient_id' => $p->id, 'consultation_id' => $note?->id, 'booking_id' => null, 'concept' => $concept,
            'amount' => $amount, 'currency' => 'BOB', 'method' => $method, 'registered_by' => 900001]);
        $c->status = 'paid';
        $c->paid_at = now()->subDays($daysAgo);
        $c->save();
    }

    protected function issue(DocumentService $docs, Patient $p, string $kind, $note, array $fill = [], bool $issue = true, ?string $name = null): void
    {
        $u = $this->doctor();
        $tpl = DocumentTemplate::where('tenant_id', $p->tenant_id)->where('kind', $kind)->when($name, fn ($q) => $q->where('name', $name))->first();
        $d = $docs->generate($p, $tpl, $u, $note);
        $body = (string) $d->body;
        foreach ($fill as $from => $to) {
            $body = str_replace($from, $to, $body);
        }
        // El responsable sale del contacto de la ficha (el cliente de Office se vincula al final, con las reservas).
        $body = str_replace(['Responsable: __________', 'Yo, __________,'], ['Responsable: ' . $p->emergency_name, 'Yo, ' . $p->emergency_name . ','], $body);
        $docs->update($d, $d->title, $body, $u);
        if ($issue) {
            $docs->issue($d->fresh(), $u);
        }
    }


    protected function guardian(string $name, string $relation): array
    {
        return ['emergency_name' => $name, 'emergency_phone' => '00000000', 'emergency_relation' => $relation];
    }

    protected function sofia(int $t, object $u): void
    {
        $p = $this->patient($t, 1, ['name' => 'Sofía Quispe Mamani', 'birth_date' => now()->subYears(3)->subMonths(4)->toDateString(), 'sex' => 'female', 'blood_type' => 'O+', 'occupation' => 'Preescolar', 'city' => 'La Paz']
            + $this->guardian('Marta Mamani Cruz', 'Madre') + ['personal_history' => 'Nacida a término. Lactancia materna exclusiva hasta los 6 meses.', 'family_history' => 'Madre con anemia en la infancia.', 'habits' => 'Duerme 10 horas. Come poca carne.']);
        $this->note($p, 'control', 120, ['reason' => 'Control de niña sana', 'feeding' => 'Come 4 veces al día; rechaza carnes y verduras. Toma leche 2 vasos al día.', 'development' => 'Habla frases de 3 palabras, sube escaleras alternando pies. Asiste a guardería.', 'physical_exam' => 'Activa, eutrófica a la inspección. Sin hallazgos patológicos.', 'vaccines_review' => 'Carnet revisado, esquema al día.', 'guidance' => 'Variar la alimentación, limitar pantallas, ofrecer hierro dietético.', 'plan' => 'Control en 4 meses.'],
            ['heart_rate' => '100', 'resp_rate' => '24', 'temperature_c' => '36.6', 'weight_kg' => '13.8', 'height_cm' => '94'],
            fn ($c, $i, $user) => $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'Z00.1', 'certainty' => 'confirmed', 'primary' => true], $user));
        $c = $this->note($p, 'control', 10, ['reason' => 'Control de niña sana y palidez referida por la madre', 'feeding' => 'Sin cambios: poca carne y verduras.', 'development' => 'Adecuado para su edad.', 'physical_exam' => 'Palidez leve de mucosas. Resto sin alteraciones.', 'vaccines_review' => 'Al día.', 'guidance' => 'Dieta rica en hierro; sulfato ferroso según indicación; no dar con leche.', 'plan' => 'Hemograma de control en 8 semanas.'],
            ['heart_rate' => '104', 'resp_rate' => '24', 'temperature_c' => '36.5', 'weight_kg' => '14.4', 'height_cm' => '95.5'],
            function ($c, $i, $user) {
                $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'D50.9', 'certainty' => 'presumptive', 'primary' => true], $user);
                $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'Z00.1', 'certainty' => 'confirmed'], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Sulfato ferroso (gotas)', 'active_ingredient' => 'Sulfato ferroso', 'presentation' => 'gotas', 'dose' => 'según peso, indicada por el médico', 'route' => 'oral', 'frequency' => 'una vez al día', 'duration' => '8 semanas', 'instructions' => 'Lejos de la leche. Las heces pueden oscurecerse.'], $user);
                $i->addOrderItem($c, ['exam_name' => 'Hemograma completo', 'instructions' => 'Control a las 8 semanas.'], $user);
            });
        $this->lab($p, 'Hemoglobina', '11.2', 'g/dL', 10, '11.5', '13.5');
        $this->charge($p, $c, 'Control pediátrico', '150', 'cash', 10);
        $v = new VaccineService();
        $v->apply($p, ['vaccine_name' => 'Influenza (estacional)', 'applied_on' => now()->subDays(200)->toDateString(), 'lot' => 'FLU-2291', 'manufacturer' => 'Fabricante Demo S.A.', 'route_site' => 'IM, muslo', 'next_due_on' => now()->addDays(165)->toDateString()], $u);
    }

    protected function mateo(int $t, object $u): void
    {
        $p = $this->patient($t, 2, ['name' => 'Mateo Choque Lima', 'birth_date' => now()->subYears(8)->subMonths(2)->toDateString(), 'sex' => 'male', 'blood_type' => 'A+', 'occupation' => 'Estudiante, 3.º de primaria', 'city' => 'El Alto', 'allergies' => '']
            + $this->guardian('Gladys Lima', 'Madre') + ['habits' => 'Fútbol dos veces por semana. Poco consumo de agua.']);
        $c = $this->note($p, 'consultation', 6, ['reason' => 'Dolor de oído derecho y fiebre', 'current_illness' => 'Dos días de otalgia derecha y fiebre de 38.5 °C tras un resfrío. Llanto por el dolor en la noche.', 'background' => 'Otitis previa a los 4 años.', 'systems_review' => 'Sin vómitos ni diarrea. Come poco por el dolor.',
            'physical_exam' => 'Membrana timpánica derecha eritematosa y abombada. Oído izquierdo normal. Orofaringe sin exudado.', 'impression' => 'Otitis media aguda derecha.', 'treatment' => 'Analgesia y antibiótico oral.', 'alarm_signs' => 'Fiebre persistente, secreción por el oído, dolor detrás de la oreja, decaimiento.', 'plan' => 'Control en 72 horas.'],
            ['heart_rate' => '98', 'resp_rate' => '22', 'temperature_c' => '38.3', 'spo2' => '98', 'weight_kg' => '27', 'height_cm' => '128'],
            function ($c, $i, $user) {
                $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'H66.9', 'certainty' => 'confirmed', 'primary' => true], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Amoxicilina (suspensión)', 'active_ingredient' => 'Amoxicilina', 'presentation' => 'suspensión oral', 'dose' => 'según peso, indicada por el médico', 'route' => 'oral', 'frequency' => 'cada 8 horas', 'duration' => '7 días'], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Paracetamol (gotas)', 'active_ingredient' => 'Paracetamol', 'presentation' => 'gotas', 'dose' => 'según peso', 'route' => 'oral', 'frequency' => 'cada 8 horas si hay fiebre o dolor', 'duration' => '3 días'], $user);
            });
        (new VaccineService())->apply($p, ['vaccine_name' => 'Influenza (estacional)', 'applied_on' => now()->subDays(330)->toDateString(), 'lot' => 'FLU-2105', 'manufacturer' => 'Fabricante Demo S.A.', 'route_site' => 'IM, deltoides', 'next_due_on' => now()->addDays(20)->toDateString()], $u);
        $this->charge($p, $c, 'Consulta pediátrica', '200', 'transfer', 6);
        $this->issue(new DocumentService(), $p, 'certificate', $c, ['__________ días' => '3 días'], true, 'Certificado médico escolar');
    }

    protected function lucia(int $t, object $u): void
    {
        $p = $this->patient($t, 3, ['name' => 'Lucía Rojas Vargas', 'birth_date' => now()->subYears(1)->subMonths(2)->toDateString(), 'sex' => 'female', 'blood_type' => 'O+', 'city' => 'La Paz']
            + $this->guardian('Patricia Vargas', 'Madre') + ['personal_history' => 'Nacida a término, peso adecuado. Lactancia materna con alimentación complementaria desde los 6 meses.']);
        $v = new VaccineService();
        foreach ([[420, '1.ª dosis'], [360, '2.ª dosis'], [300, '3.ª dosis']] as [$d, $label]) {
            $v->apply($p, ['vaccine_name' => 'Hepatitis B', 'dose_label' => $label, 'applied_on' => now()->subDays($d)->toDateString(), 'lot' => 'HB-' . (3300 + $d), 'manufacturer' => 'Fabricante Demo S.A.', 'route_site' => 'IM, muslo'], $u);
        }
        $c = $this->note($p, 'control', 30, ['reason' => 'Control de niña sana de 12 meses', 'feeding' => 'Come de la olla familiar; leche materna por la noche.', 'development' => 'Camina con apoyo, dice 2 palabras, pinza fina presente.', 'physical_exam' => 'Activa, reactiva. Fontanela cerrada. Desarrollo psicomotor acorde a la edad.', 'vaccines_review' => 'Carnet revisado.', 'guidance' => 'Introducir variedad de alimentos; evitar miel y exceso de sal; seguridad en casa al empezar a caminar.', 'plan' => 'Próximo control en 3 meses.'],
            ['heart_rate' => '110', 'resp_rate' => '28', 'temperature_c' => '36.6', 'weight_kg' => '10.2', 'height_cm' => '78'],
            fn ($c, $i, $user) => $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'Z00.1', 'certainty' => 'confirmed', 'primary' => true], $user));
        $this->charge($p, $c, 'Control de niño sano', '150', 'cash', 30);
    }

    protected function thiago(int $t, object $u): void
    {
        $p = $this->patient($t, 4, ['name' => 'Thiago Villca Condori', 'birth_date' => now()->subMonths(7)->toDateString(), 'sex' => 'male', 'blood_type' => 'B+', 'city' => 'La Paz']
            + $this->guardian('Rubén Villca', 'Padre') + ['personal_history' => 'Nacido a término. Lactancia materna exclusiva hasta los 6 meses.', 'family_history' => 'Hermano mayor con asma.']);
        $c = $this->note($p, 'consultation', 2, ['reason' => 'Tos, mocos y respiración agitada de 3 días', 'current_illness' => 'Inicio con rinorrea y tos; desde ayer respira rápido y rechaza parte de las tomas. Fiebre baja intermitente.', 'background' => 'Sin hospitalizaciones previas. Asiste a guardería.', 'systems_review' => 'Orina menos que lo habitual. Sin vómitos.',
            'physical_exam' => 'Taquipnea con tiraje subcostal leve. Sibilancias espiratorias difusas. Mucosas húmedas.', 'impression' => 'Bronquiolitis aguda.', 'treatment' => 'Medidas de soporte: lavados nasales, fraccionar las tomas, vigilar hidratación.', 'alarm_signs' => 'Rechazo total de tomas, tiraje marcado, labios morados, decaimiento, pausas al respirar.', 'plan' => 'Control en 24-48 horas; consulta de urgencia si hay signos de alarma.'],
            ['heart_rate' => '148', 'resp_rate' => '52', 'temperature_c' => '37.8', 'spo2' => '93', 'weight_kg' => '7.8', 'height_cm' => '67'],
            function ($c, $i, $user) {
                $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'J21.9', 'certainty' => 'confirmed', 'primary' => true], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Solución salina nasal', 'presentation' => 'gotas/spray nasal', 'dose' => '2-3 gotas por fosa', 'route' => 'nasal', 'frequency' => 'antes de cada toma y cuando haya mocos', 'duration' => '7 días'], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Paracetamol (gotas)', 'active_ingredient' => 'Paracetamol', 'presentation' => 'gotas', 'dose' => 'según peso', 'route' => 'oral', 'frequency' => 'cada 8 horas si hay fiebre', 'duration' => '3 días'], $user);
            });
        $this->charge($p, $c, 'Consulta pediátrica', '150', 'cash', 2);
        $this->issue(new DocumentService(), $p, 'referral', $c, ['A: __________ (especialidad / centro)' => 'A: Neumología pediátrica', 'Motivo de la derivación: __________' => 'Motivo de la derivación: Seguimiento de bronquiolitis con sibilancias recurrentes (antecedente familiar de asma)', 'Se adjuntan estudios disponibles: __________' => 'Se adjuntan estudios disponibles: ninguno'], false);
    }

    protected function valentina(int $t, object $u): void
    {
        $p = $this->patient($t, 5, ['name' => 'Valentina Mendoza Pinto', 'birth_date' => now()->subYears(11)->subMonths(3)->toDateString(), 'sex' => 'female', 'blood_type' => 'B+', 'occupation' => 'Estudiante, 6.º de primaria', 'city' => 'La Paz', 'allergies' => 'Polvo y ácaros (rinitis)', 'chronic_conditions' => 'Asma leve intermitente']
            + $this->guardian('Rosa Pinto', 'Madre') + ['habits' => 'Natación una vez por semana.', 'family_history' => 'Padre con rinitis alérgica.']);
        $c = $this->note($p, 'control', 45, ['reason' => 'Control de asma', 'evolution' => '', 'physical_exam' => 'Sibilancias espiratorias leves. Sin tiraje.', 'guidance' => 'Evitar desencadenantes (polvo, frío brusco); técnica correcta del inhalador con cámara.', 'plan' => 'Control en 3 meses.', 'feeding' => '', 'development' => 'Buen rendimiento escolar; faltó 2 días por tos.'],
            ['heart_rate' => '84', 'resp_rate' => '20', 'temperature_c' => '36.6', 'spo2' => '96', 'weight_kg' => '36', 'height_cm' => '143'],
            function ($c, $i, $user) {
                $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'J45.9', 'certainty' => 'confirmed', 'primary' => true], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Salbutamol inhalador', 'active_ingredient' => 'Salbutamol', 'strength' => '100 mcg/dosis', 'presentation' => 'inhalador con cámara espaciadora', 'dose' => '2 inhalaciones', 'route' => 'inhalatoria', 'frequency' => 'a demanda, máximo cada 4 horas', 'quantity' => '1 inhalador'], $user);
            });
        $this->charge($p, $c, 'Control pediátrico', '150', 'cash', 45);
        $this->issue(new DocumentService(), $p, 'constancia', $c);
    }

    protected function camila(int $t, object $u): void
    {
        $p = $this->patient($t, 6, ['name' => 'Camila Torrez Rojas', 'birth_date' => now()->subYears(15)->subMonths(7)->toDateString(), 'sex' => 'female', 'blood_type' => 'A+', 'occupation' => 'Estudiante, 4.º de secundaria', 'city' => 'La Paz']
            + $this->guardian('Elena Rojas', 'Madre') + ['habits' => 'Duerme 6 horas por estudio. Pantallas hasta tarde. Poco desayuno.', 'family_history' => 'Madre con migraña.']);
        $c = $this->note($p, 'consultation', 14, ['reason' => 'Dolor de cabeza frecuente hace 2 meses', 'current_illness' => 'Dolor opresivo bilateral, al final de la tarde, 2-3 veces por semana, sin náuseas ni alteraciones visuales. Mejora con descanso.', 'background' => 'Sin traumatismos. Ciclos menstruales regulares.', 'systems_review' => 'Sin vómitos, sin fiebre, sin alteraciones de la marcha.',
            'physical_exam' => 'Neurológico normal. Fondo de ojo sin alteraciones. Tensión del cuello y trapecios.', 'impression' => 'Cefalea tensional, probable relación con sueño insuficiente y estrés escolar.', 'treatment' => 'Medidas no farmacológicas: higiene del sueño, horarios de comida, reducir pantallas.', 'alarm_signs' => 'Vómitos, dolor nocturno que despierta, cambios visuales, debilidad, fiebre con rigidez de cuello.', 'plan' => 'Diario de cefaleas y control en 4 semanas.'],
            ['systolic' => '108', 'diastolic' => '68', 'heart_rate' => '78', 'resp_rate' => '16', 'temperature_c' => '36.5', 'spo2' => '98', 'weight_kg' => '52', 'height_cm' => '160'],
            fn ($c, $i, $user) => $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'G44.2', 'certainty' => 'presumptive', 'primary' => true], $user));
        $this->charge($p, $c, 'Consulta pediátrica', '180', 'cash', 14);
        $this->issue(new DocumentService(), $p, 'consent', null, ['la vacuna a aplicar: __________' => 'la vacuna a aplicar: refuerzo de esquema (a definir)'], false, 'Consentimiento de vacunación (responsable)');
    }

    protected function diego(int $t, object $u): void
    {
        $p = $this->patient($t, 7, ['name' => 'Diego Ticona Aliaga', 'birth_date' => now()->subYears(5)->subMonths(6)->toDateString(), 'sex' => 'male', 'blood_type' => 'O-', 'occupation' => 'Estudiante, kínder', 'city' => 'La Paz']
            + $this->guardian('Juana Aliaga', 'Madre'));
        $c = $this->note($p, 'consultation', 3, ['reason' => 'Dolor de garganta y fiebre', 'current_illness' => 'Dos días de odinofagia, fiebre de 38.6 °C y decaimiento. Sin tos.', 'background' => 'Amigdalitis hace un año.', 'systems_review' => 'Dolor abdominal leve, sin vómitos.',
            'physical_exam' => 'Amígdalas hipertróficas con exudado blanquecino. Adenopatías cervicales dolorosas.', 'impression' => 'Amigdalitis aguda.', 'treatment' => 'Antibiótico oral y analgesia.', 'alarm_signs' => 'Dificultad para tragar saliva, babeo, rigidez de cuello, rash, fiebre mayor a 3 días.', 'plan' => 'Control en 48-72 horas.'],
            ['heart_rate' => '112', 'resp_rate' => '22', 'temperature_c' => '38.6', 'spo2' => '98', 'weight_kg' => '19', 'height_cm' => '110'],
            function ($c, $i, $user) {
                $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'J03.9', 'certainty' => 'confirmed', 'primary' => true], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Amoxicilina (suspensión)', 'active_ingredient' => 'Amoxicilina', 'presentation' => 'suspensión oral', 'dose' => 'según peso, indicada por el médico', 'route' => 'oral', 'frequency' => 'cada 8-12 horas según indicación', 'duration' => '10 días'], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Paracetamol (gotas)', 'active_ingredient' => 'Paracetamol', 'presentation' => 'gotas', 'dose' => 'según peso', 'route' => 'oral', 'frequency' => 'cada 8 horas si hay fiebre o dolor', 'duration' => '3 días'], $user);
            });
        $this->charge($p, $c, 'Consulta pediátrica', '150', 'cash', 3);
        $this->issue(new DocumentService(), $p, 'certificate', $c, ['__________ días' => '3 días'], true, 'Certificado médico escolar');
    }

    protected function joaquin(int $t, object $u): void
    {
        $p = $this->patient($t, 8, ['name' => 'Joaquín Mamani Choque', 'birth_date' => now()->subYears(16)->subMonths(2)->toDateString(), 'sex' => 'male', 'blood_type' => 'A+', 'occupation' => 'Estudiante, 5.º de secundaria', 'city' => 'El Alto']
            + $this->guardian('Carmen Choque', 'Madre') + ['habits' => 'Fútbol de fin de semana. Come en la calle con frecuencia.']);
        $c = $this->note($p, 'consultation', 1, ['reason' => 'Diarrea y dolor abdominal desde ayer', 'current_illness' => 'Cinco deposiciones líquidas, sin sangre ni moco, y dolor cólico. Un vómito. Comió en puesto de la calle.', 'background' => 'Sin enfermedades crónicas.', 'systems_review' => 'Sin fiebre alta. Orina disminuida.',
            'physical_exam' => 'Hidratación regular, mucosa oral algo seca. Abdomen blando, doloroso a la palpación difusa, sin defensa.', 'impression' => 'Gastroenteritis aguda sin deshidratación grave.', 'treatment' => 'Hidratación oral y dieta suave.', 'alarm_signs' => 'Sangre en las heces, vómitos persistentes, sed intensa, orina escasa, somnolencia, dolor abdominal intenso o localizado.', 'plan' => 'Control en 48 horas si no mejora.'],
            ['systolic' => '110', 'diastolic' => '70', 'heart_rate' => '92', 'resp_rate' => '16', 'temperature_c' => '37.4', 'spo2' => '98', 'weight_kg' => '60', 'height_cm' => '172'],
            function ($c, $i, $user) {
                $i->addDiagnosis($c, ['source' => 'cie10', 'code' => 'A09', 'certainty' => 'presumptive', 'primary' => true], $user);
                $i->addPrescriptionItem($c, ['medication' => 'Sales de rehidratación oral', 'presentation' => 'sobres', 'dose' => 'un sobre disuelto según la indicación del envase', 'route' => 'oral', 'frequency' => 'a sorbos, después de cada deposición', 'duration' => 'hasta que cese la diarrea'], $user);
            });
        $this->charge($p, $c, 'Consulta pediátrica', '180', 'cash', 1);
        $this->issue(new DocumentService(), $p, 'constancia', $c);
    }

    /**
     * Reservas de ejemplo en Office para que la Agenda de hoy tenga contenido. Solo si Office tiene sucursal, servicios y
     * profesionales (configuración inicial). Los clientes llevan la nota «DEMO-AGENDA» (así --purge los borra a ellos y a
     * sus reservas, y a nada más), sin teléfono ni correo, y los avisos al cliente quedan silenciados.
     * @return int reservas creadas
     */
    protected function agenda(int $tenant): int
    {
        if (!class_exists(\Aero\Office\Models\Booking::class) || !\Schema::hasTable('aero_office_bookings')) {
            return 0;
        }
        $branch = \Aero\Office\Models\Branch::where('tenant_id', $tenant)->where('is_active', true)->first();
        if (!$branch || !\Aero\Office\Models\Service::where('tenant_id', $tenant)->exists()) {
            $this->warn('Sin sucursal/servicios en Office: no se cargan reservas de ejemplo.');

            return 0;
        }

        \Aero\Office\Classes\Notifier::$muted = true;
        $svc = app(\Aero\Office\Classes\BookingService::class);
        $service = fn (string $name) => \Aero\Office\Models\Service::where('tenant_id', $tenant)->where('name', $name)->first();
        $worker = fn (string $like) => \Aero\Office\Models\Worker::where('tenant_id', $tenant)->where('name', 'like', $like . '%')->value('id');
        $monday = now()->next(\Carbon\Carbon::MONDAY);

        // [n del paciente DEMO-n, cliente que reserva, servicio, fecha-hora, estado, profesional]
        $plan = [
            [1, 'Marta Mamani Cruz (madre de Sofía)', 'Control pediátrico', now()->setTime(8, 0), 'completed', null],
            [4, 'Rubén Villca (padre de Thiago)', 'Consulta pediátrica', now()->setTime(9, 0), 'confirmed', null],
            [3, 'Patricia Vargas (madre de Lucía)', 'Control de niño sano', now()->setTime(9, 45), 'confirmed', null],
            [7, 'Juana Aliaga (madre de Diego)', 'Consulta pediátrica', now()->setTime(10, 30), 'pending', null],
            [2, 'Gladys Lima (madre de Mateo)', 'Control pediátrico', now()->setTime(11, 15), 'pending', null],
            [5, 'Rosa Pinto (madre de Valentina)', 'Control pediátrico', $monday->copy()->setTime(8, 30), 'confirmed', null],
            [8, 'Carmen Choque (madre de Joaquín)', 'Consulta pediátrica', $monday->copy()->setTime(9, 0), 'pending', null],
            [6, 'Elena Rojas (madre de Camila)', 'Control pediátrico', $monday->copy()->setTime(15, 0), 'confirmed', 'Dr. Marcos'],
        ];

        $customers = [];
        $made = 0;
        foreach ($plan as [$n, $who, $svcName, $at, $status, $doc]) {
            try {
                $customers[$who] ??= \Aero\Office\Models\Customer::create(['tenant_id' => $tenant, 'name' => $who, 'notes' => 'DEMO-AGENDA']);
                DB::table('aero_clinica_patients')->where('tenant_id', $tenant)->where('document', 'DEMO-' . $n)->update(['customer_id' => $customers[$who]->id]);

                $b = $svc->create($customers[$who], [
                    'branch_id' => $branch->id, 'service_id' => $service($svcName)->id, 'worker_id' => $doc ? $worker($doc) : null,
                    'starts_at' => $at, 'status' => $status === 'completed' ? 'confirmed' : $status,
                    'customer_notes' => 'Reserva de ejemplo (demostración).',
                ], 'manual', null);
                if ($status === 'completed') {
                    $svc->complete($b);
                }
                $made++;
            } catch (\Throwable $e) {
                $this->warn('Reserva omitida (' . $who . ', ' . $svcName . '): ' . $e->getMessage());
            }
        }
        \Aero\Office\Classes\Notifier::$muted = false;

        return $made;
    }

    protected function purge(int $tenant): int
    {
        $ids = Patient::withTrashed()->where('tenant_id', $tenant)->where('document', 'like', 'DEMO-%')->pluck('id')->all();
        if (!$ids) {
            $this->info('No hay demo sembrada.');

            return 0;
        }

        $byPatient = ['aero_clinica_consultations', 'aero_clinica_vitals', 'aero_clinica_diagnoses', 'aero_clinica_prescriptions', 'aero_clinica_orders',
            'aero_clinica_lab_results', 'aero_clinica_vaccinations', 'aero_clinica_documents'];
        DB::transaction(function () use ($ids, $tenant, $byPatient) {
            if (\Schema::hasTable('aero_office_customers')) {
                $custIds = DB::table('aero_office_customers')->where('tenant_id', $tenant)->where('notes', 'DEMO-AGENDA')->pluck('id')->all();
                $bookIds = DB::table('aero_office_bookings')->where('tenant_id', $tenant)->whereIn('customer_id', $custIds)->pluck('id')->all();
                DB::table('aero_office_booking_logs')->whereIn('booking_id', $bookIds)->delete();
                DB::table('aero_office_bookings')->whereIn('id', $bookIds)->delete();
                DB::table('aero_office_customers')->whereIn('id', $custIds)->delete();
            }
            $cons = DB::table('aero_clinica_consultations')->whereIn('patient_id', $ids)->pluck('id')->all();
            $rx = DB::table('aero_clinica_prescriptions')->whereIn('patient_id', $ids)->pluck('id')->all();
            $ord = DB::table('aero_clinica_orders')->whereIn('patient_id', $ids)->pluck('id')->all();
            DB::table('aero_clinica_addenda')->whereIn('consultation_id', $cons)->delete();
            foreach (DB::table('aero_clinica_files')->whereIn('patient_id', $ids)->pluck('storage_name') as $stored) {
                \Aero\Clinica\Classes\PrivateFiles::forget($tenant, $stored);
            }
            DB::table('aero_clinica_files')->whereIn('patient_id', $ids)->delete();
            DB::table('aero_clinica_charges')->whereIn('patient_id', $ids)->delete();
            DB::table('aero_clinica_prescription_items')->whereIn('prescription_id', $rx)->delete();
            DB::table('aero_clinica_order_items')->whereIn('order_id', $ord)->delete();
            foreach ($byPatient as $table) {
                if (\Schema::hasTable($table) && \Schema::hasColumn($table, 'patient_id')) {
                    DB::table($table)->whereIn('patient_id', $ids)->delete();
                }
            }
            DB::table('aero_clinica_patients')->whereIn('id', $ids)->delete();
            DB::table('aero_clinica_professionals')->where('tenant_id', $tenant)->where('user_id', 900001)->delete();
        });
        $this->info('Demo borrada: ' . count($ids) . ' pacientes.');

        return 0;
    }
}
