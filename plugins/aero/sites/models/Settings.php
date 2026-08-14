<?php namespace Aero\Sites\Models;

use Model;

/**
 * Configuración a nivel de plataforma para Aero.Sites — actualmente solo el
 * banco de imágenes usado por el generador con IA. Separado de
 * Aero\AiFields\Models\Settings (que es genérico de proveedor LLM).
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_sites_settings';

    public $settingsFields = 'fields.yaml';

    public static function getUnsplashAccessKey(): ?string
    {
        return self::get('unsplash_access_key');
    }

    public static function isImageSourcingConfigured(): bool
    {
        return !empty(self::getUnsplashAccessKey());
    }

    public static function getImageCacheTtlDays(): int
    {
        return (int) self::get('image_cache_ttl_days', 30);
    }
}
