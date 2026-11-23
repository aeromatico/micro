<?php namespace Aero\Social\Classes;

use Aero\Social\Models\Account;

/** Alta/actualización de cuentas conectadas respetando el límite del plan. */
class AccountSync
{
    /** null = sin límite. Se descuenta a medida que se agregan cuentas. */
    public ?int $free;

    public int $skipped = 0;

    public function __construct(public int $tenantId)
    {
        $this->free = Limits::remaining($tenantId);
    }

    /**
     * ¿Cabe? Las que ya están activas no consumen cupo (reconectar siempre
     * funciona); las nuevas o reactivadas sí.
     */
    public function fits(string $network, string $externalId): bool
    {
        if ($this->free === null) {
            return true;
        }

        $active = Account::where(['tenant_id' => $this->tenantId, 'network' => $network, 'external_id' => $externalId])
            ->where('status', 'active')->exists();
        if ($active) {
            return true;
        }
        if ($this->free < 1) {
            $this->skipped++;

            return false;
        }
        $this->free--;

        return true;
    }

    public function upsert(string $network, string $externalId, array $data): Account
    {
        $account = Account::firstOrNew(['tenant_id' => $this->tenantId, 'network' => $network, 'external_id' => $externalId]);
        $account->fill($data + ['last_error' => null, 'checked_at' => now()]);
        $account->status = 'active';
        $account->save();

        return $account;
    }
}
