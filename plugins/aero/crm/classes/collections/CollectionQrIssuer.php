<?php namespace Aero\Crm\Classes\Collections;

use Aero\Crm\Models\CollectionItem;
use Aero\Crm\Models\CrmSettings;

/**
 * Genera (o reutiliza) el QR de cobro de aero/pay para un CollectionItem,
 * usando la cuenta bancaria configurada en CrmSettings.collections_bank_account_id
 * — no hay cuenta por cobro individual, es una sola por tenant (decisión de
 * negocio, más simple para el caso normal de un solo negocio con una cuenta).
 *
 * El QR se pide con un vencimiento largo (180 días desde hoy) porque una
 * cobranza puede seguir vigente semanas por la cascada de recordatorios —
 * el vencimiento corto por defecto de QRBO está pensado para un cobro
 * puntual, no para esto.
 */
class CollectionQrIssuer
{
    protected const QR_VALIDITY_DAYS = 180;

    public function issueFor(CollectionItem $item): ?\Aero\Pay\Models\QrCode
    {
        if (!class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            return null;
        }

        $existing = $item->getQrCode();
        if ($existing && $existing->status === 'pending') {
            return $existing;
        }

        $settings = CrmSettings::where('tenant_id', $item->tenant_id)->first();
        if (!$settings || !$settings->collections_bank_account_id) {
            return null;
        }

        // Sin el where('tenant_id', ...): la autorización real es que
        // CrmSettings DE ESTE TENANT apunte a esta cuenta — el dueño de la
        // cuenta puede ser otro (varios negocios del mismo dueño compartiendo
        // un solo API bancario). Ver QrIssuer::issue()/tenantId.
        $bankAccount = \Aero\Pay\Models\BankAccount::active()
            ->find($settings->collections_bank_account_id);

        if (!$bankAccount) {
            return null;
        }

        try {
            $qrCode = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $bankAccount,
                amount: (float) $item->amount,
                currency: $item->currency ?: 'BOB',
                description: $item->concept,
                externalReference: 'crm-cobro-' . $item->id . '-' . now()->timestamp,
                origin: 'crm',
                dueDate: now()->addDays(self::QR_VALIDITY_DAYS)->toDateString(),
                tenantId: $item->tenant_id,
            );
        } catch (\Throwable $e) {
            return null;
        }

        $item->payment_reference = $qrCode->internal_reference;
        $item->save();

        return $qrCode;
    }
}
