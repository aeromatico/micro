<?php namespace Aero\Church\Models;

use Model;

/**
 * Aporte (diezmo, ofrenda, donación). Efectivo y transferencia nacen confirmados; el QR nace pendiente y
 * lo confirma el pago en Pay. Un aporte confirmado no se edita: se anula. Anónimo = sin identidad guardada.
 */
class Contribution extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const METHODS = ['cash' => 'Efectivo', 'transfer' => 'Transferencia bancaria', 'qr' => 'QR bancario'];
    public const STATUSES = ['pending' => 'Pendiente', 'confirmed' => 'Confirmado', 'cancelled' => 'Anulado'];

    /** Campos que no cambian una vez confirmado. */
    protected const LOCKED = ['amount', 'currency', 'date', 'fund_id', 'method', 'member_id', 'donor_name', 'is_anonymous'];

    public $table = 'aero_church_contributions';

    public $attributes = ['method' => 'cash', 'currency' => 'BOB', 'is_anonymous' => false];

    public $fillable = [
        'tenant_id', 'branch_id', 'fund_id', 'member_id', 'donor_name', 'is_anonymous', 'amount', 'currency', 'date',
        'method', 'reference', 'notes',
    ];

    public $rules = [
        'fund_id'  => 'required|integer',
        'amount'   => 'required|numeric|min:0.01',
        'currency' => 'required|in:BOB,USD',
        'date'     => 'required|date',
        'method'   => 'required|in:cash,transfer,qr',
    ];

    public $dates = ['date', 'confirmed_at'];

    public $belongsTo = [
        'fund'   => [Fund::class, 'key' => 'fund_id'],
        'branch' => [Branch::class, 'key' => 'branch_id'],
        'member' => [Member::class, 'key' => 'member_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['fund_id' => Fund::class, 'branch_id' => Branch::class, 'member_id' => Member::class]);
    }

    public function beforeSave(): void
    {
        // Anónimo: no se guarda identidad alguna.
        if ($this->is_anonymous) {
            $this->member_id = null;
            $this->donor_name = null;
        }
        if ($this->member_id) {
            $this->donor_name = null;
        }

        if ($this->exists && $this->getOriginal('status') !== 'pending' && $this->getOriginal('status') !== null) {
            $changed = array_intersect(self::LOCKED, array_keys($this->getDirty()));
            if ($changed) {
                throw new \ApplicationException('Un aporte confirmado o anulado no se edita: anúlalo y regístralo de nuevo.');
            }
        }
    }

    public function beforeCreate(): void
    {
        $this->status = $this->method === 'qr' ? 'pending' : 'confirmed';
        $this->confirmed_at = $this->status === 'confirmed' ? now() : null;
        $this->recorded_by = \BackendAuth::getUser()?->id;
    }

    public function beforeDelete(): void
    {
        if ($this->status === 'confirmed') {
            throw new \ApplicationException('Un aporte confirmado no se elimina (queda en la contabilidad): anúlalo.');
        }
    }

    public function afterCreate(): void
    {
        if ($this->status === 'confirmed') {
            \Event::fire('aero.church.contributionConfirmed', [$this]);
        }
    }

    public function getMethodOptions(): array
    {
        return self::METHODS;
    }

    public function getCurrencyOptions(): array
    {
        return ['BOB' => 'Bolivianos (BOB)', 'USD' => 'Dólares (USD)'];
    }

    public function getFundIdOptions(): array
    {
        return Fund::visible()->open()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getMemberIdOptions(): array
    {
        return Member::visible()->orderBy('first_name')->get()->pluck('full_name', 'id')->all();
    }

    public function scopeConfirmed($query)
    {
        return $query->where($this->getTable() . '.status', 'confirmed');
    }

    /** Nombre para mostrar y para el comprobante. */
    public function getDonorLabelAttribute(): string
    {
        return $this->is_anonymous ? 'Anónimo' : ($this->member?->full_name ?: ($this->donor_name ?: '—'));
    }

    public function markConfirmed(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }
        $this->status = 'confirmed';
        $this->confirmed_at = now();
        $this->save();
        \Event::fire('aero.church.contributionConfirmed', [$this]);

        return true;
    }

    public function markCancelled(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }
        $wasConfirmed = $this->status === 'confirmed';
        $this->status = 'cancelled';
        $this->save();
        if ($wasConfirmed) {
            \Event::fire('aero.church.contributionCancelled', [$this]);
        }

        return true;
    }
}
