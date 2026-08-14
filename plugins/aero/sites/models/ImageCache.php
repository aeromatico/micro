<?php namespace Aero\Sites\Models;

use Model;

/**
 * Cache de imágenes resueltas desde un banco externo (Unsplash) por
 * keywords, para no repetir búsquedas y no agotar el rate limit gratuito.
 */
class ImageCache extends Model
{
    public $table = 'aero_sites_image_cache';

    public $fillable = ['keywords_hash', 'keywords', 'url', 'attribution', 'provider'];

    public $timestamps = true;

    public static function normalizeHash(string $keywords): string
    {
        return md5(mb_strtolower(trim($keywords)));
    }
}
