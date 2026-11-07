<?php namespace Aero\Clinica\Console;

use Aero\Clinica\Classes\ClinicalItems;
use Aero\Clinica\Classes\ConsultationService;
use Aero\Clinica\Classes\DefaultDocuments;
use Aero\Clinica\Classes\DocumentService;
use Aero\Clinica\Classes\FileService;
use Aero\Clinica\Classes\ReferenceRanges;
use Aero\Clinica\Classes\SuggestedTemplates;
use Aero\Clinica\Classes\VaccineService;
use Aero\Clinica\Models\Analyte;
use Aero\Clinica\Models\Cie10;
use Aero\Clinica\Models\Charge;
use Aero\Clinica\Models\Consultation;
use Aero\Clinica\Models\DiagnosisCatalog;
use Aero\Clinica\Models\ExamCatalog;
use Aero\Clinica\Models\ClinicSettings;
use Aero\Clinica\Models\DocumentTemplate;
use Aero\Clinica\Models\NoteTemplate;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Professional;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Datos 100 % ficticios para la demostración (solo en el tenant maestro). Cada paciente lleva el documento
 * «DEMO-n»: con eso --purge los borra sin tocar nada más. No crea clientes de Office, no activa recordatorios
 * y no usa teléfonos ni correos.
 */
class SeedDemo extends Command
{
    protected $signature = 'clinica:seed-demo {tenant=25 : Tenant (el maestro por defecto)} {--purge : Borra lo sembrado (documentos DEMO-*)} {--force : Permite otro tenant}';

    protected $description = 'Siembra o borra pacientes ficticios de demostración (solo tenant maestro)';

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
        SuggestedTemplates::load($tenant);
        DefaultDocuments::load($tenant);
        Analyte::ensureDefaults($tenant);
        ReferenceRanges::load($tenant);
        $this->catalogs($tenant);

        $u = $this->doctor();
        $prof = Professional::forUser($tenant, $u);
        $prof->forceFill(['title' => 'Dra.', 'specialty' => 'Medicina interna', 'license' => 'DEMO-0000'])->saveQuietly();

        $this->marta($tenant, $u);
        $this->carlos($tenant, $u);
        $this->lucia($tenant, $u);
        $this->jorge($tenant, $u);
        $this->rosa($tenant, $u);
        $this->andres($tenant, $u);
        $this->elena($tenant, $u);
        $this->pedro($tenant, $u);

        $this->info('Demo sembrada: ' . Patient::where('tenant_id', $tenant)->where('document', 'like', 'DEMO-%')->count() . ' pacientes ficticios.');

        return 0;
    }

    protected function catalogs(int $tenant): void
    {
        foreach (['Hemograma completo' => 'lab', 'Perfil lipídico' => 'lab', 'Creatinina sérica' => 'lab', 'Glucosa en ayunas' => 'lab', 'Examen general de orina' => 'lab',
                  'Radiografía de tórax' => 'imaging', 'Radiografía de columna lumbosacra' => 'imaging', 'Ecografía abdominal' => 'imaging', 'Electrocardiograma' => 'other'] as $n => $cat) {
            ExamCatalog::firstOrCreate(['tenant_id' => $tenant, 'name' => $n], ['category' => $cat, 'is_active' => true]);
        }
        foreach ([['Lumbalgia mecánica', 'M54.5'], ['Rinofaringitis aguda', 'J00'], ['Control de enfermedad crónica', null]] as [$l, $code]) {
            DiagnosisCatalog::firstOrCreate(['tenant_id' => $tenant, 'source' => 'custom', 'entry_key' => $code ?: md5(mb_strtolower($l))], ['code' => $code, 'label' => $l, 'pinned' => false]);
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

    protected function marta(int $t, object $u): void
    {
        $p = $this->patient($t, 1, [
            'name' => 'Marta Quispe Flores', 'birth_date' => now()->subYears(58)->subMonths(3)->toDateString(), 'sex' => 'female', 'blood_type' => 'O+',
            'occupation' => 'Comerciante', 'marital_status' => 'married', 'address' => 'Calle Los Pinos 123', 'city' => 'La Paz',
            'emergency_name' => 'Rubén Quispe', 'emergency_phone' => '00000000', 'emergency_relation' => 'Esposo',
            'allergies' => 'Penicilina (urticaria)', 'medications' => 'Losartán 50 mg c/24 h; Metformina 850 mg c/12 h',
            'chronic_conditions' => "Hipertensión arterial\nDiabetes mellitus tipo 2", 'habits' => 'No fuma. Sin alcohol. Camina 30 min, 3 veces por semana.',
            'personal_history' => 'Hipertensa desde hace 10 años. Diabetes diagnosticada hace 6 años.', 'family_history' => 'Madre con diabetes. Padre con infarto a los 65.',
        ]);

        foreach ([[150, '152', '94', '84.0', '7.4', '168'], [90, '144', '90', '82.5', '7.0', '150'], [20, '134', '84', '80.0', '6.6', '118']] as $i => [$d, $sys, $dia, $kg, $a1c, $glu]) {
            $this->note($p, 'control', $d, [
                'reason' => 'Control de hipertensión y diabetes',
                'evolution' => $i === 0 ? 'Refiere cefalea ocasional y poliuria. Adherencia irregular al tratamiento.' : ($i === 1 ? 'Mejor adherencia. Sin cefalea. Continúa con dieta.' : 'Asintomática. Cumple el tratamiento y la caminata.'),
                'physical_exam' => 'Cardiopulmonar sin alteraciones. Pulsos pedios presentes. Sin edemas.',
                'plan' => $i === 2 ? 'Mantener tratamiento. Control en 3 meses con perfil lipídico.' : 'Reforzar dieta y ejercicio. Control en 2 meses.',
            ], ['systolic' => $sys, 'diastolic' => $dia, 'heart_rate' => '76', 'resp_rate' => '16', 'temperature_c' => '36.4', 'spo2' => '96', 'weight_kg' => $kg, 'height_cm' => '158'],
                function ($c, $items, $user) use ($i) {
                    $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10', 'certainty' => 'confirmed', 'primary' => true], $user);
                    $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'E11.9', 'certainty' => 'confirmed'], $user);
                    $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Losartán 50 mg', 'active_ingredient' => 'Losartán potásico', 'strength' => '50 mg', 'presentation' => 'tabletas', 'dose' => '1 tableta', 'route' => 'oral', 'frequency' => 'cada 24 horas', 'duration' => '90 días', 'quantity' => '90', 'instructions' => 'Por la mañana.'], $user);
                    $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Metformina 850 mg', 'active_ingredient' => 'Metformina clorhidrato', 'strength' => '850 mg', 'presentation' => 'tabletas', 'dose' => '1 tableta', 'route' => 'oral', 'frequency' => 'cada 12 horas', 'duration' => '90 días', 'quantity' => '180', 'instructions' => 'Con las comidas.'], $user);
                    if ($i === 2) {
                        $items->addOrderItem($c, ['exam_name' => 'Perfil lipídico'], $user);
                        $items->addOrderItem($c, ['exam_name' => 'Creatinina sérica'], $user);
                    }
                });
            $this->lab($p, 'Hemoglobina glicosilada (HbA1c)', $a1c, '%', $d, null, '5.6');
            $this->lab($p, 'Glucosa en ayunas', $glu, 'mg/dL', $d, '70', '99');
        }
        $this->lab($p, 'Colesterol total', '231', 'mg/dL', 20, null, '199');
        $this->lab($p, 'Triglicéridos', '182', 'mg/dL', 20, null, '149');

        $v = new VaccineService();
        $v->apply($p, ['vaccine_name' => 'Influenza (estacional)', 'applied_on' => now()->subDays(200)->toDateString(), 'lot' => 'FLU-2291', 'manufacturer' => 'Fabricante Demo S.A.', 'route_site' => 'IM, deltoides izq.', 'next_due_on' => now()->addDays(165)->toDateString()], $u);
        $v->apply($p, ['vaccine_name' => 'Tétanos y difteria (dT)', 'dose_label' => 'Refuerzo', 'applied_on' => now()->subDays(400)->toDateString(), 'lot' => 'DT-1180', 'manufacturer' => 'Fabricante Demo S.A.', 'route_site' => 'IM, deltoides der.'], $u);
    }

    protected function carlos(int $t, object $u): void
    {
        $p = $this->patient($t, 2, [
            'name' => 'Carlos Mamani Choque', 'birth_date' => now()->subYears(34)->subMonths(5)->toDateString(), 'sex' => 'male', 'blood_type' => 'A+',
            'occupation' => 'Docente', 'marital_status' => 'single', 'city' => 'El Alto',
            'emergency_name' => 'Elena Choque', 'emergency_phone' => '00000000', 'emergency_relation' => 'Madre',
            'allergies' => '', 'habits' => 'Fumador ocasional. Futbol los fines de semana.',
        ]);
        $c = $this->note($p, 'consultation', 3, [
            'reason' => 'Dolor de garganta y tos de 3 días',
            'current_illness' => 'Inicio hace 3 días con odinofagia, rinorrea y tos seca. Fiebre de 38 °C el primer día. Sin disnea.',
            'systems_review' => 'Sin dolor torácico, sin vómitos, sin diarrea. Apetito conservado.',
            'physical_exam' => 'Faringe eritematosa sin exudado. Adenopatías cervicales pequeñas, móviles. Murmullo vesicular conservado.',
            'impression' => 'Infección respiratoria alta de probable origen viral.',
            'treatment' => 'Sintomático. Hidratación y reposo.',
            'plan' => 'Reconsultar si hay fiebre mayor a 3 días o dificultad respiratoria.',
        ], ['systolic' => '118', 'diastolic' => '76', 'heart_rate' => '88', 'resp_rate' => '18', 'temperature_c' => '37.8', 'spo2' => '98', 'weight_kg' => '72', 'height_cm' => '174'],
            function ($c, $items, $user) {
                $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'J06.9', 'certainty' => 'presumptive', 'primary' => true], $user);
                $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Paracetamol 500 mg', 'active_ingredient' => 'Paracetamol', 'strength' => '500 mg', 'presentation' => 'tabletas', 'dose' => '1 tableta', 'route' => 'oral', 'frequency' => 'cada 8 horas si hay fiebre o dolor', 'duration' => '3 días', 'quantity' => '9'], $user);
            });

        $docs = new DocumentService();
        $tpl = DocumentTemplate::where('tenant_id', $t)->where('kind', 'certificate')->first();
        $d = $docs->generate($p, $tpl, $u, $c);
        $docs->update($d, $d->title, str_replace('__________ días de reposo', '2 días de reposo', (string) $d->body), $u);
        $docs->issue($d->fresh(), $u);
    }

    protected function lucia(int $t, object $u): void
    {
        $p = $this->patient($t, 3, [
            'name' => 'Lucía Rojas Vargas', 'birth_date' => now()->subYears(1)->subMonths(2)->toDateString(), 'sex' => 'female', 'blood_type' => 'O+',
            'emergency_name' => 'Patricia Vargas', 'emergency_phone' => '00000000', 'emergency_relation' => 'Madre', 'city' => 'La Paz',
        ]);
        $v = new VaccineService();
        foreach ([[420, '1.ª dosis'], [360, '2.ª dosis'], [300, '3.ª dosis']] as [$d, $label]) {
            $v->apply($p, ['vaccine_name' => 'Hepatitis B', 'dose_label' => $label, 'applied_on' => now()->subDays($d)->toDateString(), 'lot' => 'HB-' . (3300 + $d), 'manufacturer' => 'Fabricante Demo S.A.', 'route_site' => 'IM, muslo'], $u);
        }
        $this->note($p, 'consultation', 30, [
            'reason' => 'Control de niño sano',
            'physical_exam' => 'Activa, reactiva. Fontanela cerrada. Desarrollo psicomotor acorde a la edad.',
            'plan' => 'Continuar lactancia complementaria. Próximo control en 3 meses.',
        ], ['heart_rate' => '110', 'resp_rate' => '28', 'temperature_c' => '36.6', 'weight_kg' => '10.2', 'height_cm' => '78'],
            fn ($c, $items, $user) => $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'Z00.1', 'certainty' => 'confirmed', 'primary' => true], $user));
    }

    protected function jorge(int $t, object $u): void
    {
        $p = $this->patient($t, 4, [
            'name' => 'Jorge Villca Condori', 'birth_date' => now()->subYears(71)->subMonths(1)->toDateString(), 'sex' => 'male', 'blood_type' => 'A-',
            'occupation' => 'Jubilado', 'marital_status' => 'widowed', 'city' => 'La Paz',
            'emergency_name' => 'Silvia Villca', 'emergency_phone' => '00000000', 'emergency_relation' => 'Hija',
            'allergies' => 'AINE (broncoespasmo)', 'medications' => 'Salbutamol inhalador a demanda',
            'chronic_conditions' => 'EPOC', 'habits' => 'Exfumador (suspendió hace 8 años).',
        ]);
        $c = $this->note($p, 'control', 12, [
            'reason' => 'Control de EPOC',
            'evolution' => 'Disnea al subir escaleras. Dos exacerbaciones leves en el último año.',
            'physical_exam' => 'Tórax con espiración prolongada, sibilancias aisladas.',
            'plan' => 'Continuar broncodilatador. Valoración por neumología.',
        ], ['systolic' => '138', 'diastolic' => '82', 'heart_rate' => '84', 'resp_rate' => '22', 'temperature_c' => '36.5', 'spo2' => '91', 'weight_kg' => '66', 'height_cm' => '165'],
            function ($c, $items, $user) {
                $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'J44.9', 'certainty' => 'confirmed', 'primary' => true], $user);
                $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Salbutamol 100 mcg/dosis', 'active_ingredient' => 'Salbutamol', 'strength' => '100 mcg/dosis', 'presentation' => 'inhalador', 'dose' => '2 inhalaciones', 'route' => 'inhalatoria', 'frequency' => 'a demanda, máximo cada 4 horas', 'quantity' => '1 inhalador'], $user);
            });
        $docs = new DocumentService();
        $tpl = DocumentTemplate::where('tenant_id', $t)->where('kind', 'referral')->first();
        $docs->generate($p, $tpl, $u, $c); // queda en borrador: la derivación se completa a mano
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

    protected function issue(DocumentService $docs, Patient $p, string $kind, $note, array $fill = [], bool $issue = true): void
    {
        $u = $this->doctor();
        $tpl = DocumentTemplate::where('tenant_id', $p->tenant_id)->where('kind', $kind)->first();
        $d = $docs->generate($p, $tpl, $u, $note);
        $body = (string) $d->body;
        foreach ($fill as $from => $to) {
            $body = str_replace($from, $to, $body);
        }
        $docs->update($d, $d->title, $body, $u);
        if ($issue) {
            $docs->issue($d->fresh(), $u);
        }
    }

    protected function rosa(int $t, object $u): void
    {
        $p = $this->patient($t, 5, ['name' => 'Rosa Mendoza Pinto', 'birth_date' => now()->subYears(29)->toDateString(), 'sex' => 'female', 'blood_type' => 'B+', 'occupation' => 'Contadora', 'marital_status' => 'single', 'city' => 'La Paz',
            'emergency_name' => 'Tomás Mendoza', 'emergency_phone' => '00000000', 'emergency_relation' => 'Padre', 'allergies' => 'Polvo y ácaros (rinitis)', 'chronic_conditions' => 'Asma leve intermitente', 'habits' => 'No fuma. Corre 2 veces por semana.']);
        $c = $this->note($p, 'control', 45, ['reason' => 'Control de asma', 'evolution' => 'Dos crisis leves en el último mes, ambas con el frío. Usa salbutamol a demanda.', 'physical_exam' => 'Sibilancias espiratorias leves. Sin tiraje.', 'plan' => 'Evitar desencadenantes. Control en 3 meses.'],
            ['systolic' => '110', 'diastolic' => '70', 'heart_rate' => '82', 'resp_rate' => '20', 'temperature_c' => '36.6', 'spo2' => '96', 'weight_kg' => '58', 'height_cm' => '162'],
            function ($c, $items, $user) {
                $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'J45.9', 'certainty' => 'confirmed', 'primary' => true], $user);
                $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Salbutamol 100 mcg/dosis', 'active_ingredient' => 'Salbutamol', 'strength' => '100 mcg/dosis', 'presentation' => 'inhalador', 'dose' => '2 inhalaciones', 'route' => 'inhalatoria', 'frequency' => 'a demanda', 'quantity' => '1 inhalador'], $user);
            });
        $this->lab($p, 'Hemoglobina', '12.8', 'g/dL', 45, '12', '16');
        $this->charge($p, $c, 'Control médico', '150', 'cash', 45);
        $this->issue(new DocumentService(), $p, 'constancia', $c);
    }

    protected function andres(int $t, object $u): void
    {
        $p = $this->patient($t, 6, ['name' => 'Andrés Choque Lima', 'birth_date' => now()->subYears(8)->subMonths(2)->toDateString(), 'sex' => 'male', 'blood_type' => 'O+', 'city' => 'El Alto',
            'emergency_name' => 'Gladys Lima', 'emergency_phone' => '00000000', 'emergency_relation' => 'Madre']);
        $c = $this->note($p, 'consultation', 6, ['reason' => 'Dolor de oído derecho y fiebre', 'current_illness' => 'Dos días de otalgia derecha y fiebre de 38.5 °C, tras un resfrío.', 'systems_review' => 'Sin vómitos ni diarrea. Come poco por el dolor.',
            'physical_exam' => 'Membrana timpánica derecha eritematosa y abombada. Oído izquierdo normal. Orofaringe sin exudado.', 'impression' => 'Otitis media aguda derecha.', 'treatment' => 'Analgesia y antibiótico oral.', 'plan' => 'Control en 72 horas; antes si hay empeoramiento.'],
            ['heart_rate' => '98', 'resp_rate' => '22', 'temperature_c' => '38.3', 'spo2' => '98', 'weight_kg' => '27', 'height_cm' => '128'],
            function ($c, $items, $user) {
                $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'H66.9', 'certainty' => 'confirmed', 'primary' => true], $user);
                $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Amoxicilina 500 mg', 'active_ingredient' => 'Amoxicilina', 'strength' => '500 mg', 'presentation' => 'cápsulas', 'dose' => 'según peso, indicada por el médico', 'route' => 'oral', 'frequency' => 'cada 8 horas', 'duration' => '7 días', 'quantity' => '21'], $user);
                $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Paracetamol 500 mg', 'active_ingredient' => 'Paracetamol', 'strength' => '500 mg', 'presentation' => 'tabletas', 'dose' => 'según peso', 'route' => 'oral', 'frequency' => 'cada 8 horas si hay fiebre o dolor', 'duration' => '3 días'], $user);
            });
        $v = new VaccineService();
        $v->apply($p, ['vaccine_name' => 'Influenza (estacional)', 'applied_on' => now()->subDays(330)->toDateString(), 'lot' => 'FLU-2105', 'manufacturer' => 'Fabricante Demo S.A.', 'route_site' => 'IM, deltoides', 'next_due_on' => now()->addDays(20)->toDateString()], $u);
        $this->charge($p, $c, 'Consulta pediátrica', '200', 'transfer', 6);
        $this->issue(new DocumentService(), $p, 'certificate', $c, ['__________ días de reposo' => '3 días de reposo']);
    }

    protected function elena(int $t, object $u): void
    {
        $p = $this->patient($t, 7, ['name' => 'Elena Torrez Rojas', 'birth_date' => now()->subYears(45)->subMonths(7)->toDateString(), 'sex' => 'female', 'blood_type' => 'AB+', 'occupation' => 'Enfermera', 'marital_status' => 'divorced', 'city' => 'La Paz',
            'emergency_name' => 'Camila Torrez', 'emergency_phone' => '00000000', 'emergency_relation' => 'Hija', 'habits' => 'Duerme 5 horas. Café en exceso. Sin actividad física.', 'family_history' => 'Madre con depresión.']);
        $c = $this->note($p, 'consultation', 14, ['reason' => 'Nerviosismo, insomnio y palpitaciones hace 2 meses', 'current_illness' => 'Estrés laboral. Dificultad para conciliar el sueño y palpitaciones sin dolor torácico.', 'systems_review' => 'Sin pérdida de peso. Sin temblor.',
            'physical_exam' => 'Ansiosa, colaboradora. Tiroides no palpable. Ruidos cardíacos rítmicos.', 'impression' => 'Trastorno de ansiedad, probable.', 'treatment' => 'Medidas de higiene del sueño. Psicoeducación.', 'plan' => 'Control en 4 semanas. Valorar derivación a psicología.'],
            ['systolic' => '126', 'diastolic' => '82', 'heart_rate' => '94', 'resp_rate' => '18', 'temperature_c' => '36.5', 'spo2' => '98', 'weight_kg' => '64', 'height_cm' => '160'],
            function ($c, $items, $user) {
                $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'F41.9', 'certainty' => 'presumptive', 'primary' => true], $user);
                $items->addOrderItem($c, ['exam_name' => 'Electrocardiograma'], $user);
            });
        (new ConsultationService())->addendum($c, 'La paciente llamó para informar que el ECG fue normal. Se mantiene el plan.', $u);
        $this->charge($p, $c, 'Consulta', '180', 'cash', 14);
        $this->issue(new DocumentService(), $p, 'referral', $c, ['A: __________ (especialidad / centro)' => 'A: Psicología clínica', 'Motivo de la derivación: __________' => 'Motivo de la derivación: Manejo no farmacológico de ansiedad', 'Se adjuntan estudios disponibles: __________' => 'Se adjunta: ECG'], false);
        $this->issue(new DocumentService(), $p, 'consent', null, ['procedimiento: __________' => 'procedimiento: evaluación psicológica'], false);
    }

    protected function pedro(int $t, object $u): void
    {
        $p = $this->patient($t, 8, ['name' => 'Pedro Ticona Aliaga', 'birth_date' => now()->subYears(63)->toDateString(), 'sex' => 'male', 'blood_type' => 'O-', 'occupation' => 'Albañil', 'marital_status' => 'married', 'city' => 'La Paz',
            'emergency_name' => 'Juana Aliaga', 'emergency_phone' => '00000000', 'emergency_relation' => 'Esposa', 'habits' => 'Trabajo físico pesado. Fuma 5 cigarrillos al día.', 'chronic_conditions' => 'Hipertensión arterial']);
        $c = $this->note($p, 'consultation', 2, ['reason' => 'Dolor lumbar de 10 días', 'current_illness' => 'Dolor lumbar bajo tras levantar peso, sin irradiación ni debilidad. Mejora con reposo.', 'systems_review' => 'Sin fiebre, sin alteraciones urinarias.',
            'physical_exam' => 'Contractura paravertebral lumbar. Lasègue negativo. Fuerza y reflejos conservados.', 'impression' => 'Lumbalgia mecánica.', 'treatment' => 'Analgesia y medidas posturales.', 'plan' => 'Radiografía si no mejora en 2 semanas.'],
            ['systolic' => '148', 'diastolic' => '92', 'heart_rate' => '78', 'resp_rate' => '16', 'temperature_c' => '36.4', 'spo2' => '95', 'weight_kg' => '81', 'height_cm' => '168'],
            function ($c, $items, $user) {
                $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'M54.5', 'certainty' => 'confirmed', 'primary' => true], $user);
                $items->addDiagnosis($c, ['source' => 'cie10', 'code' => 'I10', 'certainty' => 'confirmed'], $user);
                $items->addOrderItem($c, ['exam_name' => 'Radiografía de columna lumbosacra', 'instructions' => 'Si no mejora en 2 semanas.'], $user);
                $items->addPrescriptionItem($c, ['source' => 'free', 'medication' => 'Paracetamol 500 mg', 'active_ingredient' => 'Paracetamol', 'strength' => '500 mg', 'presentation' => 'tabletas', 'dose' => '1 tableta', 'route' => 'oral', 'frequency' => 'cada 8 horas', 'duration' => '5 días', 'quantity' => '15'], $user);
            });
        $this->lab($p, 'Creatinina', '1.4', 'mg/dL', 2, '0.7', '1.3');
        $this->charge($p, $c, 'Consulta', '180', 'cash', 2);

        // Archivo clínico de ejemplo (texto), cifrado en disco como cualquier adjunto.
        $tmp = tempnam(sys_get_temp_dir(), 'demo') . '.txt';
        file_put_contents($tmp, "RESULTADO DE EJEMPLO (demostración)\nPaciente ficticio: Pedro Ticona Aliaga\nCreatinina: 1.4 mg/dL (ref. 0.7-1.3)\n");
        (new FileService())->attach($p, new \Illuminate\Http\UploadedFile($tmp, 'creatinina-ejemplo.txt', 'text/plain', null, true),
            ['kind' => 'lab', 'title' => 'Creatinina (ejemplo)', 'taken_on' => now()->subDays(2)->toDateString(), 'consultation_id' => $c->id], $u);
        @unlink($tmp);
        $this->issue(new DocumentService(), $p, 'report', $c, ["Resumen clínico y evolución:\n__________" => "Resumen clínico y evolución:\nLumbalgia mecánica sin signos de alarma.", "Tratamiento y recomendaciones:\n__________" => "Tratamiento y recomendaciones:\nAnalgesia, reposo relativo y control de la presión arterial."]);
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
