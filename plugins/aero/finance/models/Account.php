<?php namespace Aero\Finance\Models;

use Aero\Finance\Classes\TenantOwned;
use Model;

class Account extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_finance_accounts';

    public const TYPES = [
        'asset' => 'Activo', 'liability' => 'Pasivo', 'equity' => 'Patrimonio',
        'income' => 'Ingreso', 'expense' => 'Egreso',
    ];

    public $fillable = ['tenant_id', 'code', 'name', 'type', 'parent_id', 'system_key', 'is_active'];

    public $rules = [
        'code' => 'required|max:20',
        'name' => 'required|max:150',
        'type' => 'required|in:asset,liability,equity,income,expense',
    ];

    public $attributeNames = ['code' => 'código', 'name' => 'nombre', 'type' => 'tipo'];

    public function getTypeOptions(): array
    {
        return self::TYPES;
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible(['parent_id' => self::class]);

        $dup = self::where('tenant_id', $this->tenant_id)->where('code', $this->code)
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))->exists();
        if ($dup) {
            throw new \ApplicationException('Ya existe una cuenta con el código ' . $this->code . '.');
        }
    }

    public function beforeDelete(): void
    {
        if (JournalLine::where('account_id', $this->id)->exists() || $this->system_key) {
            throw new \ApplicationException('No se puede eliminar: es una cuenta del sistema o tiene movimientos. Desactívela.');
        }
    }

    public function getLabelAttribute(): string
    {
        return $this->code . ' · ' . $this->name;
    }

    /** Cuentas por naturaleza: activo y egreso suben por el debe; el resto por el haber. */
    public function isDebitNature(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
