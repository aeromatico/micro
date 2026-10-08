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

                if ($fee > 0 && Billing::active()) {
                    $tx = Billing::charge($tenantId, $fee, 'workspaces.hire', "Contratación de {$staff->name}",
                        // Con la hora: quien se despidió y se vuelve a contratar paga de nuevo.
                        "workspaces.hire.{$tenantId}.{$staff->id}." . now()->timestamp, ['staff_id' => $staff->id]);
                    $charged = $fee;
                }

                Hire::create(['tenant_id' => $tenantId, 'staff_id' => $staff->id, 'fee_charged' => $charged, 'hired_at' => now(), 'credit_transaction_id' => $tx]);
            });
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            throw new \DomainException("No tienes puntos suficientes para contratar a {$staff->name} ({$fee} pts).");
        }
        catch (\Illuminate\Database\QueryException $e) {
            // Solo la carrera de dos contrataciones a la vez es «ya está»; cualquier otro fallo de BD no se disfraza.
            if (Hire::forTenant($tenantId)->where('staff_id', $staff->id)->exists()) {
                throw new \DomainException("{$staff->name} ya está en tu equipo.");
            }

            throw $e;
        }

        \Event::fire('aero.workspaces.staffHired', [$tenantId, $staff->id, $charged]);

        if ($charged > 0) {
            \Event::fire('aero.workspaces.charged', [$tenantId, 'hire', $charged, (int) $staff->id, (int) $staff->id]);
        }

        $staff->load(['skills', 'rate', 'taskRateRows']);

        return ['agent' => Workspace::agent($staff, true), 'charged' => $charged];
    }

    /**
     * Despedir: el agente sale del equipo y se pierde lo pagado (la contratación
     * no se reembolsa). Volver a contratarlo cobra de nuevo.
     *
     * @throws \DomainException
     */
    public static function dismiss(int $tenantId, string $slug): array
    {
        $staff = Staff::where('slug', $slug)->first();

        if (!$staff || $staff->is_orchestrator) {
            throw new \DomainException('Ese agente no se puede despedir.');
        }

        $hire = Hire::forTenant($tenantId)->where('staff_id', $staff->id)->first();

        if (!$hire) {
            throw new \DomainException("{$staff->name} no está en tu equipo.");
        }

        $hire->delete();
        \Event::fire('aero.workspaces.staffDismissed', [$tenantId, (int) $staff->id]);

        return ['dismissed' => $staff->name, 'refunded' => 0];
    }

    /** Qué pasaría al contratar, sin hacerlo (para confirmar antes). */
    public static function preview(int $tenantId, string $slug): array
    {
        $staff = static::find($slug);

        return [
            'agent'       => $staff->name,
            'already'     => Hire::forTenant($tenantId)->where('staff_id', $staff->id)->exists(),
            'hire_fee'    => (int) round((float) $staff->hire_fee),
            'chat_fee'    => Billing::rate($staff, Billing::TURN),
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
