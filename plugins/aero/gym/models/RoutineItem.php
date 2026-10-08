<?php namespace Aero\Gym\Models;

use Model;

class RoutineItem extends Model
{
    public $table = 'aero_gym_routine_items';

    public $timestamps = false;

    public $fillable = ['routine_id', 'day_label', 'exercise', 'sets', 'reps', 'rest_seconds', 'notes', 'sort_order'];

    public $belongsTo = ['routine' => [Routine::class, 'key' => 'routine_id']];
}
