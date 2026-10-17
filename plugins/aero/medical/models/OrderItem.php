<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\GuardsSignedNote;
use Aero\Medical\Classes\TenantOwned;
use Model;

class OrderItem extends Model
{
    use TenantOwned;
    use GuardsSignedNote;

    public $table = 'aero_medical_order_items';

    public $fillable = ['tenant_id', 'order_id', 'position', 'category', 'exam_name', 'instructions'];

    public $casts = ['exam_name' => 'encrypted', 'instructions' => 'encrypted'];

    public $belongsTo = ['order' => [Order::class, 'key' => 'order_id']];

    protected function noteId(): ?int
    {
        return Order::where('id', $this->order_id)->value('consultation_id');
    }

    public function beforeSave(): void
    {
        $this->assertNoteOpen($this->noteId());
    }

    public function beforeDelete(): void
    {
        $this->assertNoteOpen($this->noteId());
    }
}
