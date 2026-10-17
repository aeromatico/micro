<?php namespace Aero\Medical\Models;

use Model;

/** Catálogo GLOBAL LINAME (AGEMED). Vacío hasta que se cargue un CSV verificado (ver data/README.md). */
class Liname extends Model
{
    public $table = 'aero_medical_liname';

    public $timestamps = false;

    public $fillable = ['code', 'name', 'form', 'concentration', 'atc', 'restricted'];

    public static function search(string $q, int $limit = 15): \Illuminate\Support\Collection
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return collect();
        }

        $query = static::query();
        foreach (preg_split('/\s+/', $q) as $w) {
            $query->where('name', 'like', '%' . addcslashes($w, '%_\\') . '%');
        }

        return $query->orderBy('name')->limit($limit)->get();
    }
}
