<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\GuardsSignedNote;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Orden de exámenes de una nota. */
class Order extends Model
{
    use TenantOwned;
    use GuardsSignedNote;

    public $table = 'aero_medical_orders';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'notes'];

    public $casts = ['notes' => 'encrypted'];

    public $hasMany = ['items' => [OrderItem::class, 'key' => 'order_id', 'order' => 'position asc']];

    public function beforeSave(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }

    public function beforeDelete(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }
}
