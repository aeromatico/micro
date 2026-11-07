<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\GuardsSignedNote;
use Aero\Clinica\Classes\TenantOwned;
use Model;

class OrderItem extends Model
{
    use TenantOwned;
    use GuardsSignedNote;

    public $table = 'aero_clinica_order_items';

    public $fillable = ['tenant_id', 'order_id', 'position', 'category', 'exam_name', 'instructions', 'status', 'resulted_at'];

    public $casts = ['exam_name' => 'encrypted', 'instructions' => 'encrypted', 'resulted_at' => 'datetime'];

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
