<?php namespace Aero\Medical\Models;

use Model;

/** Catálogo GLOBAL CIE-10 (OPS/OMS, español). Sin tenant_id; solo lectura desde el panel. */
class Cie10 extends Model
{
    public $table = 'aero_medical_cie10';

    public $timestamps = false;

    public $fillable = ['code', 'level', 'parent', 'chapter', 'title'];

    /** Búsqueda por código (prefijo) o por palabras del nombre. */
    public static function search(string $q, int $limit = 25): \Illuminate\Support\Collection
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return collect();
        }

        $query = static::query();
        if (preg_match('/^[A-Za-z]\d{1,2}(\.\d?)?$/', $q)) {
            $query->where('code', 'like', strtoupper($q) . '%')->orderBy('code');
        } else {
            foreach (preg_split('/\s+/', $q) as $w) {
                $query->where('title', 'like', '%' . addcslashes($w, '%_\\') . '%');
            }
            $query->orderBy('code');
        }

        return $query->limit($limit)->get();
    }
}
