<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Settings;

/**
 * Los puntos de Workspaces son créditos de Aero.Credits. Este es el único lugar
 * que lo toca, así Billing se prueba con un libro falso (Credits usa triggers de
 * MySQL y no corre en SQLite).
 */
class CreditsLedger
{
    public function balance(int $tenantId): int
    {
        return \Aero\Credits\Classes\Credits::balance($tenantId, Settings::creditTypeCode());
    }

    /**
     * @return int id del movimiento
     * @throws \Aero\Credits\Classes\Exceptions\InsufficientCreditsException
     * @throws \DomainException si el tipo de crédito no existe
     */
    public function charge(int $tenantId, int $amount, string $actionCode, array $context): int
    {
        $type = \Aero\Credits\Models\CreditType::findByCode(Settings::creditTypeCode());

        if (!$type) {
            throw new \DomainException('El tipo de crédito de Workspaces no está configurado.');
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
