<?php namespace Aero\Invoicing\Models;

use Model;

/** CUIS/CUFD vigentes de un emisor (la CUFD dura 24 h). Un registro por renovación. */
class Cufd extends Model
{
    public $table = 'aero_invoicing_cufds';

    public $fillable = ['issuer_id', 'tenant_id', 'cuis', 'cufd', 'control_code', 'address', 'valid_until'];

    protected $casts = ['valid_until' => 'datetime'];

    public $belongsTo = ['issuer' => Issuer::class];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where($this->getTable() . '.tenant_id', $tenantId) : $query->whereRaw('1 = 0');
    }
}
