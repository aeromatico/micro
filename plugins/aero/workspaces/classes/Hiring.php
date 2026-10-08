<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Hire;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Staff;

/**
 * Contratar a un agente. Reglas (todas del servidor, no de la pantalla):
 *  - El tenant sale de quien llama, nunca de los argumentos.
 *  - Solo agentes activos y que no sean el orquestador (ese ya viene incluido).
 *  - Un tenant contrata a cada agente una sola vez (también lo exige la BD).
 *  - Con el cobro encendido y tarifa > 0, se cobra en puntos (créditos) en la
 *    misma transacción; sin saldo, no se contrata.
 */
class Hiring
{
    /**
     * @return array{agent: array, charged: int}
     * @throws \DomainException con un mensaje apto para mostrar tal cual
     */
    public static function hire(int $tenantId, string $slug, ?int $userId = null): array
    {
        $staff = static::find($slug);
        $fee = (int) round((float) $staff->hire_fee);

        if (Hire::forTenant($tenantId)->where('staff_id', $staff->id)->exists()) {
            throw new \DomainException("{$staff->name} ya está en tu equipo.");
        }

        $charged = 0;

        try {
            \DB::transaction(function () use ($tenantId, $staff, $fee, $userId, &$charged) {
                $tx = null;

                if ($fee > 0 && Settings::chargeEnabled() && class_exists(\Aero\Credits\Classes\Credits::class)) {
                    $type = \Aero\Credits\Models\CreditType::findByCode(Settings::creditTypeCode());

                    if (!$type) {
                        throw new \DomainException('El tipo de crédito de Workspaces no está configurado.');
                    }

                    $tx = \Aero\Credits\Classes\Credits::chargeRaw($tenantId, $type, $fee, 'workspaces.hire', [
                        'source_plugin'   => 'Aero.Workspaces',
                        'reason'          => "Contratación de {$staff->name}",
                        'idempotency_key' => "workspaces.hire.{$tenantId}.{$staff->id}",
                    ]);
                    $charged = $fee;
                }

                Hire::create(['tenant_id' => $tenantId, 'staff_id' => $staff->id, 'fee_charged' => $charged, 'hired_at' => now()]);
            });
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            throw new \DomainException("No tienes puntos suficientes para contratar a {$staff->name} ({$fee} pts).");
        }
        catch (\Illuminate\Database\QueryException $e) {
            throw new \DomainException("{$staff->name} ya está en tu equipo.");
        }

        \Event::fire('aero.workspaces.staffHired', [$tenantId, $staff->id, $charged]);

        $staff->load(['skills', 'rate', 'taskRateRows']);

        return ['agent' => Workspace::agent($staff, true), 'charged' => $charged];
    }

    /** Qué pasaría al contratar, sin hacerlo (para confirmar antes). */
    public static function preview(int $tenantId, string $slug): array
    {
        $staff = static::find($slug);

        return [
            'agent'       => $staff->name,
            'already'     => Hire::forTenant($tenantId)->where('staff_id', $staff->id)->exists(),
            'hire_fee'    => (int) round((float) $staff->hire_fee),
            'would_charge' => Settings::chargeEnabled() ? (int) round((float) $staff->hire_fee) : 0,
            'points'      => Workspace::points($tenantId),
        ];
    }

    protected static function find(string $slug): Staff
    {
        $staff = Staff::active()->where('slug', $slug)->first();

        if (!$staff) {
            throw new \DomainException('Ese agente no está disponible en el mercado.');
        }

        if ($staff->is_orchestrator) {
            throw new \DomainException("{$staff->name} es el orquestador: ya forma parte de tu equipo y no se contrata.");
        }

        return $staff;
    }
}
