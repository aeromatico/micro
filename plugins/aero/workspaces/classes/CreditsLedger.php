<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Settings;

/**
 * Los puntos de Workspaces son créditos de Aero.Credits. Este es el único lugar
 * que lo toca, así Billing se prueba con un libro falso (Credits usa triggers de
 * MySQL y no corre en SQLite).
 */
class CreditsLedger
{
    public function balance(int $tenantId, ?string $creditType = null): int
    {
        return \Aero\Credits\Classes\Credits::balance($tenantId, $creditType ?: Settings::creditTypeCode());
    }

    /**
     * @return int id del movimiento
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException
     * @throws \DomainException si el tipo de crédito no existe
     */
    public function charge(int $tenantId, int $amount, string $actionCode, array $context): int
    {
        // El tipo lo elige cada agente (`credit_type` en el contexto); sin él, el de Ajustes.
        $code = (string) ($context['credit_type'] ?? '') ?: Settings::creditTypeCode();
        unset($context['credit_type']);
        $type = \Aero\Credits\Models\CreditType::findByCode($code);

        if (!$type) {
            throw new \DomainException('El tipo de crédito «' . $code . '» no existe o no está activo.');
        }

        return (int) \Aero\Credits\Classes\Credits::chargeRaw($tenantId, $type, $amount, $actionCode, ['source_plugin' => 'Aero.Workspaces'] + $context)->id;
    }

    public function refund(int $transactionId, string $reason): void
    {
        $tx = \Aero\Credits\Models\CreditTransaction::find($transactionId);

        if ($tx) {
            \Aero\Credits\Classes\Credits::refund($tx, $reason);
        }
    }
}
