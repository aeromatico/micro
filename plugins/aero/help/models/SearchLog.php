<?php namespace Aero\Help\Models;

use Model;

/** Lo que la gente buscó en la ayuda y cuántos resultados obtuvo: el backlog de contenido. */
class SearchLog extends Model
{
    public $table = 'aero_help_searches';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'user_id', 'query', 'area', 'results', 'created_at'];

    protected $dates = ['created_at'];

    public function scopeWithoutResults($query)
    {
        return $query->where('results', 0);
    }
}
