<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Addendum;
use Aero\Medical\Models\BookingPatient;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\NoteTemplate;
use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Professional;
use Aero\Medical\Models\Vitals;
use Illuminate\Support\Facades\DB;

/**
 * Toda mutación de notas pasa por aquí: abrir (con o sin reserva), autoguardar,
 * firmar y adendar. Las reglas de autoría e inmutabilidad se imponen también
 * en el modelo; esto añade el flujo con Office y la bitácora.
 */
class ConsultationService
{
    public const MAX_TEXT = 20000;

    /** Abre una nota en borrador para el paciente, con la plantilla del negocio. */
    public function start(Patient $patient, string $kind, $user, array $opts = []): Consultation
    {
        if (!isset(DefaultTemplates::KINDS[$kind])) {
            throw new MedicalException('Tipo de nota no válido.');
        }
        if (!$patient->is_active) {
            throw new MedicalException('El paciente está inactivo.');
        }

        $tenantId = (int) $patient->tenant_id;
        $booking = $opts['booking'] ?? null;

        if ($booking) {
            // Una reserva = una nota: si ya existe, se retoma.
            $existing = Consultation::where('tenant_id', $tenantId)->where('booking_id', $booking->id)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($patient, $kind, $user, $opts, $tenantId, $booking) {
            $template = NoteTemplate::resolve($tenantId, $kind);
            $profile = Professional::forUser($tenantId, $user);

            $content = [];
            if ($booking && $booking->customer_notes && collect($template->sections)->contains('key', 'reason')) {
                $content['reason'] = (string) $booking->customer_notes;
            }

            $c = new Consultation([
                'tenant_id'         => $tenantId,
                'patient_id'        => $patient->id,
                'kind'              => $kind,
                'template_id'       => $template->id,
                'template_name'     => $template->name,
                'sections'          => $template->sections,
                'content'           => $content,
                'user_id'           => $user->id,
                'worker_id'         => $opts['worker_id'] ?? null,
                'professional_name' => $profile->full_name,
                'booking_id'        => $booking?->id,
                'service_name'      => $booking?->service_name,
                'consulted_at'      => now(),
            ]);
            $c->status = 'draft';
            $c->save();

            Vitals::create(['tenant_id' => $tenantId, 'patient_id' => $patient->id, 'consultation_id' => $c->id, 'measured_at' => now()]);

            if ($booking) {
                BookingPatient::updateOrCreate(['tenant_id' => $tenantId, 'booking_id' => $booking->id], ['patient_id' => $patient->id]);
                $this->officeStart($booking, $user->id);
            }

            Audit::record('create', $tenantId, $patient->id, 'consultation', $c->id);

            return $c;
        });
    }

    /**
     * Guarda el borrador. Solo el autor, solo en borrador; los textos se limitan
     * a las secciones de la foto de plantilla y los signos vitales a los del modo.
     */
    public function autosave(Consultation $c, array $content, array $vitals, $user): Consultation
    {
        $this->assertAuthorDraft($c, $user);

        $keys = collect($c->sectionList())->where('type', 'text')->pluck('key')->all();
        $clean = (array) $c->content;
        foreach ($keys as $k) {
            if (array_key_exists($k, $content)) {
                $clean[$k] = mb_substr(str_replace("\r\n", "\n", (string) $content[$k]), 0, self::MAX_TEXT);
            }
        }

        $c->content = $clean;
        $c->last_autosave_at = now();
        $c->save();

        if (collect($c->sectionList())->contains('type', 'vitals')) {
            $this->saveVitals($c, $vitals);
        }

        return $c;
    }

    /** Firma: la nota queda inmutable. La reserva de Office pasa a atendida con el BookingService. */
    public function sign(Consultation $c, $user): array
    {
        $this->assertAuthorDraft($c, $user);

        $vit = $c->vitals;
        $hasVitals = $vit && collect(array_keys(VitalsSchema::editable($c->patient->kind)))->contains(fn ($f) => $vit->{$f} !== null && $vit->{$f} !== '');
        $hasText = collect((array) $c->content)->contains(fn ($v) => trim((string) $v) !== '');

        if (!$hasText && !$hasVitals) {
            throw new MedicalException('La nota está vacía: escriba algo antes de firmar.');
        }

        DB::transaction(function () use ($c, $user) {
            // Releer: la huella se calcula con lo que realmente guardó la BD (decimales redondeados).
            $c->refresh();
            $c->professional_name = Professional::forUser((int) $c->tenant_id, $user)->full_name;
            $c->status = 'signed';
            $c->signed_at = now();
            $c->signed_by = $user->id;
            $c->content_hash = $c->computeHash();
            $c->save();

            // Último peso del animal en su ficha.
            if ($c->patient->kind === Mode::ANIMAL && $c->vitals?->weight_kg) {
                $c->patient->weight_kg = $c->vitals->weight_kg;
                $c->patient->save();
            }

            app(ClinicalItems::class)->bumpFrequents($c);

            Audit::record('sign', $c->tenant_id, $c->patient_id, 'consultation', $c->id);
        });

        $warning = null;
        if ($c->booking_id) {
            $warning = $this->officeComplete($c, $user->id);
        }

        // Solo ids: Aero.Api reenvía a webhooks lo que viaja en eventos aero.*.
        \Event::fire('aero.medical.consultationSigned', [['consultation_id' => $c->id, 'patient_id' => $c->patient_id, 'tenant_id' => $c->tenant_id]]);

        return ['consultation' => $c, 'warning' => $warning];
    }

    /** Adenda a una nota firmada (la nota no se toca). */
    public function addendum(Consultation $c, string $body, $user): Addendum
    {
        if (!$c->isSigned()) {
            throw new MedicalException('Solo se agregan adendas a notas firmadas; el borrador se edita directamente.');
        }
        $body = trim($body);
        if ($body === '') {
            throw new MedicalException('Escriba el texto de la adenda.');
        }

        $a = Addendum::create([
            'tenant_id'       => $c->tenant_id,
            'consultation_id' => $c->id,
            'user_id'         => $user->id,
            'user_name'       => trim("{$user->first_name} {$user->last_name}") ?: $user->email,
            'body'            => mb_substr($body, 0, self::MAX_TEXT),
        ]);
        Audit::record('addendum', $c->tenant_id, $c->patient_id, 'addendum', $a->id);

        return $a;
    }

    public function discard(Consultation $c, $user): void
    {
        $this->assertAuthorDraft($c, $user);
        Vitals::where('consultation_id', $c->id)->delete();
        \Aero\Medical\Models\Diagnosis::where('consultation_id', $c->id)->delete();
        \Aero\Medical\Models\QuestionnaireAnswer::where('consultation_id', $c->id)->delete();
        if ($rx = \Aero\Medical\Models\Prescription::where('consultation_id', $c->id)->first()) {
            \Aero\Medical\Models\PrescriptionItem::where('prescription_id', $rx->id)->delete();
            $rx->delete();
        }
        if ($o = \Aero\Medical\Models\Order::where('consultation_id', $c->id)->first()) {
            \Aero\Medical\Models\OrderItem::where('order_id', $o->id)->delete();
            $o->delete();
        }
        $c->delete();
        Audit::record('delete', $c->tenant_id, $c->patient_id, 'consultation', $c->id);
    }

    protected function saveVitals(Consultation $c, array $input): void
    {
        $defs = VitalsSchema::editable($c->patient->kind);
        $v = Vitals::firstOrNew(['consultation_id' => $c->id]);
        $v->tenant_id = $c->tenant_id;
        $v->patient_id = $c->patient_id;
        $v->measured_at = $v->measured_at ?: now();

        foreach ($defs as $name => $def) {
            if (!array_key_exists($name, $input)) {
                continue;
            }
            $raw = is_string($input[$name]) ? trim(str_replace(',', '.', $input[$name])) : $input[$name];

            if ($raw === '' || $raw === null) {
                $v->{$name} = null;
            } elseif (isset($def['options'])) {
                $v->{$name} = array_key_exists($raw, $def['options']) ? $raw : null;
            } elseif (is_numeric($raw) && $raw >= $def['min'] && $raw <= $def['max']) {
                $v->{$name} = $raw;
            }
            // Un valor fuera de los límites de captura se ignora: se conserva el anterior.
        }

        $v->setRelation('patient', $c->patient);
        $v->save();
        $c->setRelation('vitals', $v);
    }

    protected function assertAuthorDraft(Consultation $c, $user): void
    {
        if ($c->isSigned()) {
            throw new MedicalException('La nota está firmada y no se puede modificar. Agregue una adenda.');
        }
        if ((int) $c->user_id !== (int) $user->id) {
            throw new MedicalException('Solo quien abrió la nota puede editarla y firmarla.');
        }
    }

    /**
     * Paciente que atiende una reserva: el elegido (debe ser del mismo cliente), el único del
     * cliente, o —en humano— uno creado desde el cliente. En animal, si el tutor tiene varios o
     * ninguno, hay que elegir/registrar el animal: no se adivina.
     */
    public function patientForBooking($booking, ?int $patientId = null): Patient
    {
        $tenantId = (int) $booking->tenant_id;
        $q = Patient::where('tenant_id', $tenantId)->where('customer_id', $booking->customer_id)->where('is_active', true);

        if ($patientId) {
            $p = (clone $q)->find($patientId);
            if (!$p) {
                throw new MedicalException('El paciente elegido no corresponde al cliente de la reserva.');
            }

            return $p;
        }

        $linked = BookingPatient::where('tenant_id', $tenantId)->where('booking_id', $booking->id)->value('patient_id');
        if ($linked && ($p = (clone $q)->find($linked))) {
            return $p;
        }

        $all = $q->orderBy('name')->get();
        if ($all->count() === 1) {
            return $all->first();
        }
        if ($all->count() > 1) {
            throw new MedicalException('El cliente tiene varios pacientes: elija cuál atiende esta reserva.');
        }

        if (Mode::forTenant($tenantId) === Mode::ANIMAL) {
            throw new MedicalException('El tutor aún no tiene animales registrados: registre primero al animal.');
        }

        $c = $booking->customer;
        $p = new Patient(['tenant_id' => $tenantId, 'customer_id' => $booking->customer_id, 'name' => $c->name, 'document' => $c->document ?: null]);
        $p->save();

        return $p;
    }

    // ---- Office (acoplamiento blando) ----

    public static function officeAvailable(): bool
    {
        return class_exists(\Aero\Office\Classes\BookingService::class) && class_exists(\Aero\Office\Models\Booking::class);
    }

    protected function officeStart($booking, int $userId): void
    {
        if (!static::officeAvailable() || $booking->status !== 'confirmed') {
            return;
        }
        app(\Aero\Office\Classes\BookingService::class)->startService($booking, $userId);
    }

    /** @return ?string aviso si Office no pudo cerrar la reserva (la nota ya quedó firmada) */
    protected function officeComplete(Consultation $c, int $userId): ?string
    {
        if (!static::officeAvailable()) {
            return null;
        }

        $booking = \Aero\Office\Models\Booking::where('tenant_id', $c->tenant_id)->find($c->booking_id);
        if (!$booking || $booking->isFinal()) {
            return null;
        }

        try {
            app(\Aero\Office\Classes\BookingService::class)->complete($booking, $userId);
        } catch (\Throwable $e) {
            return 'La nota se firmó, pero la reserva no pudo marcarse como atendida: ' . $e->getMessage();
        }

        return null;
    }
}
