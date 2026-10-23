<?php namespace Aero\Church\Classes;

use Illuminate\Support\Str;

/** Slug único por tenant para el contenido público. */
class Slugs
{
    public static function unique(string $modelClass, ?int $tenantId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($title) ?: 'contenido', 80, '');
        $slug = $base;
        $i = 2;
        while ($modelClass::where('tenant_id', $tenantId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
