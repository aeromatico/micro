<?php namespace Aero\Viral\Models;

use Model;

class Sample extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_viral_samples';

    /** Límite de archivo por muestra: 100 MB. */
    const MAX_BYTES = 104857600;

    public $fillable = ['application_id', 'kind', 'url', 'platform', 'path', 'mime', 'size_bytes', 'duration_seconds', 'status'];

    public $rules = [
        'application_id' => 'required|integer',
        'kind'       => 'required|in:link,file,recording',
        'url'        => 'nullable|url|max:1000',
        'size_bytes' => 'nullable|integer|max:104857600',
    ];

    public $belongsTo = [
        'application' => [Application::class, 'key' => 'application_id'],
    ];

    /** Plataforma a partir del link (TikTok, Instagram, YouTube, Facebook u otra). */
    public static function detectPlatform(string $url): string
    {
        $host = strtolower((string) parse_url(trim($url), PHP_URL_HOST) ?: trim($url));
        return match (true) {
            (bool) preg_match('/(^|\.)tiktok\.com$/', $host) => 'tiktok',
            (bool) preg_match('/(^|\.)(instagram\.com|instagr\.am)$/', $host) => 'instagram',
            (bool) preg_match('/(^|\.)(youtube\.com|youtu\.be)$/', $host) => 'youtube',
            (bool) preg_match('/(^|\.)(facebook\.com|fb\.watch|fb\.com)$/', $host) => 'facebook',
            default => 'other',
        };
    }
}
