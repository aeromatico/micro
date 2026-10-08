<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

/** Rutina personalizada de un socio, o plantilla (member_id null) para copiar. */
class Routine extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;
    use TenantOwned;

    public $purgeable = ['items_data'];

    public $table = 'aero_gym_routines';

    public $fillable = ['tenant_id', 'member_id', 'instructor_id', 'name', 'goal', 'starts_on', 'ends_on', 'notes', 'is_active'];

    public $rules = ['name' => 'required|string|max:255'];

    public $belongsTo = [
        'member'     => [Member::class, 'key' => 'member_id'],
        'instructor' => [Instructor::class, 'key' => 'instructor_id'],
    ];

    public $hasMany = ['items' => [RoutineItem::class, 'key' => 'routine_id', 'delete' => true, 'order' => 'sort_order']];

    public function getItemsDataAttribute(): array
    {
        return $this->exists ? $this->items->map(fn ($i) => $i->only(['day_label', 'exercise', 'sets', 'reps', 'rest_seconds', 'notes']))->all() : [];
    }

    public function afterSave(): void
    {
        $rows = $this->getOriginalPurgeValue('items_data');
        if (!is_array($rows)) {
            return;
        }
        $this->items()->delete();
        foreach (array_values($rows) as $i => $row) {
            if (trim((string) ($row['exercise'] ?? '')) === '') {
                continue;
            }
            $data = array_map(fn ($v) => $v === '' ? null : $v, array_only($row, ['day_label', 'exercise', 'sets', 'reps', 'rest_seconds', 'notes']));
            RoutineItem::create($data + ['routine_id' => $this->id, 'sort_order' => $i]);
        }
    }

    public function getMemberIdOptions(): array
    {
        return ['' => '— Plantilla (sin socio) —'] + Member::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getInstructorIdOptions(): array
    {
        return ['' => '— Sin instructor —'] + Instructor::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible(['member_id' => Member::class, 'instructor_id' => Instructor::class]);
        if ($this->member_id && empty($this->tenant_id)) {
            $this->tenant_id = Member::whereKey($this->member_id)->value('tenant_id');
        }
    }

    /** Copia una plantilla (o rutina) a un socio. */
    public function assignTo(Member $member): self
    {
        $copy = $this->replicate(['member_id', 'tenant_id']);
        $copy->tenant_id = $member->tenant_id;
        $copy->member_id = $member->id;
        $copy->save();

        foreach ($this->items as $item) {
            $c = $item->replicate(['routine_id']);
            $c->routine_id = $copy->id;
            $c->save();
        }

        return $copy;
    }
}
