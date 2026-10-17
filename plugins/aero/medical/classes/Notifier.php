<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\ClinicSettings;
use Aero\Medical\Models\Vaccination;

/**
 * Único punto por el que Historia clínica avisa al cliente (persona o tutor) vía Aero.Notify. Integración
 * blanda y NUNCA lanza. Lo que viaja a Notify (y queda en su registro de entregas) se limita a lo
 * imprescindible para el aviso: ninguna nota clínica, diagnóstico ni resultado.
 */
class Notifier
{
    /** Pruebas y sembradores lo activan para no escribir a números reales. */
    public static bool $muted = false;

    public static function available(): bool
    {
        return class_exists(\Aero\Notify\Classes\Notify::class);
    }

    public static function vaccineDue(Vaccination $v): bool
    {
        if (self::$muted || !self::available()) {
            return false;
        }

        try {
            $p = $v->patient;
            $customer = $p?->customer;
            if (!$p || !$customer || (!$customer->phone && !$customer->email)) {
                return false;
            }

            \Aero\Notify\Classes\Notify::fire('medical.vaccine.due', [
                'customer_name' => $customer->name,
                'patient_name'  => $p->name,
                // En personas el aviso es genérico: la vacuna concreta no sale por WhatsApp/correo.
                'vaccine_text'  => $p->kind === Mode::ANIMAL ? 'la vacuna «' . $v->vaccine_name . '»' : 'una dosis de su esquema de vacunación',
                'due_date'      => $v->next_due_on->locale('es')->translatedFormat('l d/m'),
                'business_name' => self::businessName((int) $v->tenant_id),
            ], [
                'tenant_id' => (int) $v->tenant_id,
                'actor'     => ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone],
                'dedup_key' => "medical:vaccine:{$v->id}:" . $v->next_due_on->toDateString(),
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::error('Aero.Medical: no se pudo avisar la vacuna ' . $v->id . ': ' . $e->getMessage());

            return false;
        }
    }

    public static function dewormingDue(\Aero\Medical\Models\Deworming $d): bool
    {
        if (self::$muted || !self::available()) {
            return false;
        }

        try {
            $p = $d->patient;
            $customer = $p?->customer;
            if (!$p || !$customer || (!$customer->phone && !$customer->email)) {
                return false;
            }

            \Aero\Notify\Classes\Notify::fire('medical.deworming.due', [
                'customer_name' => $customer->name,
                'patient_name'  => $p->name,
                'due_date'      => $d->next_due_on->locale('es')->translatedFormat('l d/m'),
                'business_name' => self::businessName((int) $d->tenant_id),
            ], [
                'tenant_id' => (int) $d->tenant_id,
                'actor'     => ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone],
                'dedup_key' => "medical:deworming:{$d->id}:" . $d->next_due_on->toDateString(),
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::error('Aero.Medical: no se pudo avisar la desparasitación ' . $d->id . ': ' . $e->getMessage());

            return false;
        }
    }

    protected static function businessName(int $tenantId): string
    {
        if (class_exists(\Aero\Office\Models\OfficeSettings::class) && ($n = \Aero\Office\Models\OfficeSettings::where('tenant_id', $tenantId)->value('business_name'))) {
            return (string) $n;
        }

        return class_exists(\Aero\Sites\Models\Tenant::class) ? (string) \Aero\Sites\Models\Tenant::where('id', $tenantId)->value('name') : '';
    }
}
