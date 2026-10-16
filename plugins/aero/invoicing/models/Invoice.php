<?php namespace Aero\Invoicing\Models;

use Aero\Invoicing\Classes\CurrentTenant;
use Illuminate\Support\Str;
use BackendAuth;
use Model;

/**
 * Documento fiscal. Montos con IVA 13 % incluido en el precio unitario
 * (así lo exige el SIAT): iva = total × 13 / 113.
 */
class Invoice extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public const IVA_RATE = 13;

    public const STATUSES = [
        'draft'     => 'Borrador',
        'pending'   => 'Enviando',
        'received'  => 'Recibida (sin validar)',
        'validated' => 'Válida',
        'rejected'  => 'Rechazada por el SIN',
        'voided'    => 'Anulada',
        'error'     => 'Error (reintentable)',
    ];

    public $table = 'aero_invoicing_invoices';

    public $fillable = [
        'tenant_id', 'issuer_id', 'reference', 'buyer_nit', 'buyer_doc_type', 'buyer_name', 'buyer_email', 'items',
    ];

    public $jsonable = ['items', 'messages'];

    public $rules = [
        'issuer_id'      => 'required',
        'buyer_nit'      => 'required|max:30',
        'buyer_doc_type' => 'in:1,2,3,4,5',
        'buyer_name'     => 'required',
        'buyer_email'    => 'nullable|email',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
        'total'     => 'float',
        'iva'       => 'float',
    ];

    public $belongsTo = ['issuer' => Issuer::class];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where($this->getTable() . '.tenant_id', $tenantId) : $query->whereRaw('1 = 0');
    }

    public function getIsEditableAttribute(): bool
    {
        return in_array($this->status ?: 'draft', ['draft', 'error'], true);
    }

    public function getIsVoidableAttribute(): bool
    {
        return in_array($this->status, ['validated', 'received'], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    /** @return array{total: float, iva: float} */
    public static function totals(array $items): array
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2);
        }
        $total = round($total, 2);

        return ['total' => $total, 'iva' => round($total * self::IVA_RATE / (100 + self::IVA_RATE), 2)];
    }

    public function beforeValidate(): void
    {
        // Desde el panel, un tenant solo factura con sus propios emisores.
        if (BackendAuth::check() && !CurrentTenant::isAdmin()) {
            $issuer = Issuer::find($this->issuer_id);
            if (!$issuer || (int) $issuer->tenant_id !== (int) CurrentTenant::id()) {
                throw new \October\Rain\Exception\ValidationException(['issuer_id' => 'Emisor no válido.']);
            }
        }

        $items = (array) $this->items;
        if (!count($items)) {
            throw new \October\Rain\Exception\ValidationException(['items' => 'La factura necesita al menos un ítem.']);
        }

        foreach ($items as $i => $item) {
            $n = $i + 1;
            if (blank($item['description'] ?? null)) {
                throw new \October\Rain\Exception\ValidationException(['items' => "Ítem {$n}: falta la descripción."]);
            }
            if ((float) ($item['quantity'] ?? 0) <= 0 || (float) ($item['unit_price'] ?? 0) <= 0) {
                throw new \October\Rain\Exception\ValidationException(['items' => "Ítem {$n}: cantidad y precio deben ser mayores que cero."]);
            }
            if (blank($item['sin_product_code'] ?? null) || blank($item['economic_activity'] ?? null)) {
                throw new \October\Rain\Exception\ValidationException(['items' => "Ítem {$n}: faltan el código de producto SIN y la actividad económica."]);
            }
        }
    }

    public function beforeSave(): void
    {
        if (!$this->uuid) {
            $this->uuid = (string) Str::uuid();
        }
        if (!$this->status) {
            $this->status = 'draft';
        }
        // El dueño de la factura es el dueño del emisor, siempre.
        if (!$this->exists) {
            $this->tenant_id = Issuer::find($this->issuer_id)?->tenant_id;
        }
        if (blank($this->reference)) {
            $this->reference = null;
        }

        // Los montos solo se recalculan mientras el documento es editable.
        if (!$this->exists || $this->isEditable) {
            $totals = self::totals((array) $this->items);
            $this->total = $totals['total'];
            $this->iva = $totals['iva'];
        }
    }

    public function getBuyerDocTypeOptions(): array
    {
        return [5 => 'NIT', 1 => 'CI', 2 => 'Carnet de extranjero', 3 => 'Pasaporte', 4 => 'Otro documento'];
    }

    /** Emisores a los que el usuario actual puede facturar. */
    public function getIssuerIdOptions(): array
    {
        $q = Issuer::where('is_active', true);
        if (!CurrentTenant::isAdmin()) {
            $q->forTenant(CurrentTenant::id());
        }

        return $q->orderBy('name')->get()->mapWithKeys(fn ($i) => [$i->id => "{$i->name} (NIT {$i->nit})"])->all();
    }
}
