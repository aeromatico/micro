<?php namespace Aero\Credits\Models;

use Model;

/** Cobro fallido que el plugin llamador no propagó (solo auditoría, fuera del ledger). */
class CreditChargeFailure extends Model
{
    public $table = 'aero_credits_charge_failures';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'action_code', 'source_plugin', 'reason', 'context', 'created_at'];

    public $jsonable = ['context'];

    protected $dates = ['created_at'];
}
