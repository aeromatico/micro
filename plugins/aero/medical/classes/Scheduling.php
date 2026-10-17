<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Patient;

/**
 * Agendar un control desde la ficha usando el BookingService de Aero.Office (disponibilidad, bloqueo del
 * profesional, historial y avisos incluidos): nunca se escriben reservas a mano. El motivo viaja en las
 * notas del cliente y al «Iniciar consulta» prellena la nota; lo verá recepción, así que debe ser breve y no clínico.
 */
class Scheduling
{
    public static function available(): bool
    {
        return ConsultationService::officeAvailable();
    }

    /** @return array{branches:array,services:array,workers:array} opciones del negocio para el formulario */
    public static function options(int $tenantId): array
    {
        if (!static::available()) {
            return ['branches' => [], 'services' => [], 'workers' => []];
        }

        return [
            'branches' => \Aero\Office\Models\Branch::forTenant($tenantId)->active()->orderBy('name')->pluck('name', 'id')->all(),
            'services' => \Aero\Office\Models\Service::forTenant($tenantId)->active()->orderBy('name')->pluck('name', 'id')->all(),
            'workers'  => \Aero\Office\Models\Worker::forTenant($tenantId)->active()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /**
     * @param array{branch_id?:mixed,service_id?:mixed,worker_id?:mixed,starts_at?:string,reason?:string} $in
     */
    public static function bookControl(Patient $p, array $in, $user): \Aero\Office\Models\Booking
    {
        if (!static::available()) {
            throw new MedicalException('Agendar requiere Aero.Office (Reservas).');
        }
        $customer = $p->customer_id ? \Aero\Office\Models\Customer::forTenant((int) $p->tenant_id)->find($p->customer_id) : null;
        if (!$customer) {
            throw new MedicalException('Para agendar, el paciente debe estar vinculado a un cliente de Reservas (persona o tutor).');
        }
        if (empty($in['starts_at'])) {
            throw new MedicalException('Indique la fecha y hora del control.');
        }

        try {
            return app(\Aero\Office\Classes\BookingService::class)->create($customer, [
                'branch_id' => (int) ($in['branch_id'] ?? 0), 'service_id' => (int) ($in['service_id'] ?? 0),
                'worker_id' => !empty($in['worker_id']) ? (int) $in['worker_id'] : null, 'starts_at' => $in['starts_at'],
                'customer_notes' => trim((string) ($in['reason'] ?? '')) !== '' ? mb_substr(trim((string) $in['reason']), 0, 500) : 'Control',
                'internal_notes' => 'Agendado desde Historia clínica', 'status' => 'confirmed',
            ], 'manual', $user->id);
        } catch (\Aero\Office\Classes\OfficeException $e) {
            throw new MedicalException($e->getMessage());
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            throw new MedicalException('La fecha y hora no son válidas.');
        }
    }
}
