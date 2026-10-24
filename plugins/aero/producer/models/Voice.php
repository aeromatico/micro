<?php namespace Aero\Producer\Models;

use Model;

/** Voz curada por la plataforma; el tenant solo ve las activas. */
class Voice extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_producer_voices';

    public $fillable = ['provider', 'voice_id', 'name', 'gender', 'language', 'preview_url', 'is_active', 'sort_order'];

    public $rules = [
        'provider' => 'required|in:elevenlabs,heygen',
        'voice_id' => 'required|max:64',
        'name'     => 'required|max:190',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function getProviderOptions(): array
    {
        return ['elevenlabs' => 'ElevenLabs', 'heygen' => 'HeyGen'];
    }

    public function getGenderOptions(): array
    {
        return ['male' => 'Masculina', 'female' => 'Femenina'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
