<?php namespace Aero\Hub\Classes;

use Aero\Hub\Models\HubEndpoint;
use Aero\Hub\Models\HubMediaJob;

/**
 * Cobro en Aero.Credits (opcional) para el proxy. Todo pasa por
 * `Credits::attempt/charge/chargeRaw/settle/refund` con el `action_code`
 * espejo que `HubEndpoint::afterSave()` mantiene en `CreditAction` — nunca el
 * hook automático `aero.connector.beforeRun/afterRun` (que solo soporta un
 * costo fijo por Connector, y acá hay 155 tarifas distintas por endpoint).
 */
class HubCredits
{
    public static function available(): bool
    {
        return class_exists(\Aero\Credits\Classes\Credits::class);
    }

    /** false si el endpoint no puede cobrarse todavía (falta color/costo o Aero.Credits no está instalado). */
    public static function isBillable(HubEndpoint $endpoint): bool
    {
        return static::available() && (bool) $endpoint->credit_type_id;
    }

    /**
     * Llamada síncrona (fixed/per_page/per_volume): cobra con hold, ejecuta
     * $callback(), liquida si sale bien o reembolsa si truena.
     *
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException
     */
    public static function attempt(int $tenantId, HubEndpoint $endpoint, \Closure $callback, array $context = []): mixed
    {
        return \Aero\Credits\Classes\Credits::attempt(
            $tenantId,
            $endpoint->actionCode(),
            $callback,
            $context + ['source_plugin' => 'Aero.Hub', 'reason' => $endpoint->summary ?: $endpoint->code]
        );
    }

    /**
     * Cobro adicional por volumen (per_volume), una vez conocido el tamaño
     * real de la respuesta. No bloquea: los datos ya se entregaron al tenant,
     * así que un fallo acá queda solo logueado.
     */
    public static function chargeOverage(int $tenantId, HubEndpoint $endpoint, int $units): void
    {
        if ($endpoint->pricing_type !== 'per_volume' || !$endpoint->overage_credit_cost || !$endpoint->unit_size) {
            return;
        }

        $overageUnits = (int) ceil($units / $endpoint->unit_size);

        if ($overageUnits < 1) {
            return;
        }

        try {
            $type = \Aero\Credits\Models\CreditType::find($endpoint->credit_type_id);

            if (!$type) {
                return;
            }

            \Aero\Credits\Classes\Credits::chargeRaw(
                $tenantId,
                $type,
                $overageUnits * $endpoint->overage_credit_cost,
                $endpoint->actionCode() . '.overage',
                ['source_plugin' => 'Aero.Hub', 'reason' => "Overage: {$units} resultados"]
            );
        }
        catch (\Throwable $e) {
            \Log::warning("Aero.Hub: fallo cobrando overage de {$endpoint->code}: " . $e->getMessage());
        }
    }

    /**
     * Async (/v1/media/queue): abre un hold sin ejecutar ninguna acción
     * todavía — el resultado (completed/failed) llega después por polling de
     * /v1/media/status/{jobId}, ver settleMediaJob()/refundMediaJob().
     */
    public static function openMediaHold(int $tenantId, HubEndpoint $endpoint, string $jobId): void
    {
        $tx = \Aero\Credits\Classes\Credits::charge($tenantId, $endpoint->actionCode(), [
            'source_plugin' => 'Aero.Hub',
            'reason'        => "Job de media encolado ({$jobId})",
            'hold_ttl'      => 3600,
        ]);

        HubMediaJob::create([
            'job_id'                 => $jobId,
            'tenant_id'              => $tenantId,
            'endpoint_code'          => $endpoint->code,
            'credit_transaction_id'  => $tx->id,
            'status'                 => HubMediaJob::QUEUED,
        ]);
    }

    public static function settleMediaJob(string $jobId): void
    {
        $job = HubMediaJob::unresolved()->where('job_id', $jobId)->first();

        if (!$job || !$job->credit_transaction_id) {
            return;
        }

        $tx = \Aero\Credits\Models\CreditTransaction::find($job->credit_transaction_id);

        if ($tx) {
            \Aero\Credits\Classes\Credits::settle($tx);
        }

        $job->update(['status' => HubMediaJob::COMPLETED]);
    }

    public static function refundMediaJob(string $jobId, string $reason): void
    {
        $job = HubMediaJob::unresolved()->where('job_id', $jobId)->first();

        if (!$job || !$job->credit_transaction_id) {
            return;
        }

        $tx = \Aero\Credits\Models\CreditTransaction::find($job->credit_transaction_id);

        if ($tx) {
            \Aero\Credits\Classes\Credits::refund($tx, $reason);
        }

        $job->update(['status' => HubMediaJob::FAILED]);
    }
}
