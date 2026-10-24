<?php namespace Aero\Producer\Models;

use Aero\Producer\Classes\CurrentTenant;
use Model;

/**
 * Pedido de video de un tenant: elige un estilo de la plataforma y pone guion,
 * voz e imagen. `extras` guarda lo propio de cada estilo (tema, fondo musical).
 */
class Order extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_producer_orders';

    public $fillable = [
        'tenant_id', 'style_id', 'name', 'script', 'voice_id', 'aspect_ratio', 'resolution', 'extras',
        'status', 'output_url', 'error_message',
    ];

    public $jsonable = ['extras'];

    public $attributes = ['status' => 'draft'];

    public $rules = [
        'style_id' => 'required|integer',
        'name'     => 'required|max:190',
    ];

    public $belongsTo = [
        'style' => [Style::class],
        'voice' => [Voice::class],
    ];

    public $attachOne = [
        'image' => [\System\Models\File::class],
    ];

    /** Palabras por segundo de una locución natural en español. */
    public const WORDS_PER_SECOND = 2.5;

    public function beforeCreate(): void
    {
        $this->tenant_id ??= CurrentTenant::id();
    }

    public function getTenantNameAttribute(): ?string
    {
        return CurrentTenant::name($this->tenant_id) ?? ($this->tenant_id ? '#' . $this->tenant_id : 'Plataforma');
    }

    public function getStatusOptions(): array
    {
        return ['draft' => 'Borrador', 'queued' => 'En cola', 'producing' => 'En producción', 'ready' => 'Listo', 'failed' => 'Falló'];
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->getStatusOptions()[$this->status] ?? (string) $this->status;
    }

    public function getVoiceIdOptions(): array
    {
        return Voice::active()->get()->mapWithKeys(fn ($v) => [$v->id => $v->name . ($v->gender ? ' · ' . ($v->gender === 'female' ? 'femenina' : 'masculina') : '')])->all();
    }

    public function getAspectRatioOptions(): array
    {
        return ['9:16' => 'Vertical 9:16 (móvil, Reels, TikTok)', '16:9' => 'Horizontal 16:9 (YouTube, web)'];
    }

    public function getResolutionOptions(): array
    {
        return ['720p' => '720p', '1080p' => '1080p (mejor calidad)'];
    }

    public function getBackgroundOptions(): array
    {
        $dir = storage_path('app/media/audio-backgrounds');
        $files = is_dir($dir) ? glob($dir . '/*.{mp3,wav,m4a}', GLOB_BRACE) : [];

        return collect($files)->mapWithKeys(fn ($f) => [basename($f) => pathinfo($f, PATHINFO_FILENAME)])->sort()->all();
    }

    public function getWordCountAttribute(): int
    {
        return str_word_count(strip_tags((string) $this->script), 0, 'áéíóúüñÁÉÍÓÚÜÑ');
    }

    public function getEstimatedSecondsAttribute(): int
    {
        return (int) ceil($this->word_count / self::WORDS_PER_SECOND);
    }

    public function getIsEditableAttribute(): bool
    {
        return in_array($this->status, ['draft', 'failed'], true);
    }

    /** Lo que impide enviar el pedido tal cual; vacío = listo para enviar. */
    public function problems(): array
    {
        $problems = [];
        $style = $this->style;

        if (!$style) {
            return ['Falta elegir un estilo.'];
        }
        if (trim((string) $this->script) === '') {
            $problems[] = 'Falta el guion.';
        } elseif ($this->estimated_seconds > $style->max_seconds) {
            $problems[] = "El guion dura unos {$this->estimated_seconds} s y este estilo admite {$style->max_seconds} s.";
        }
        if (!$this->voice_id) {
            $problems[] = 'Falta elegir una voz.';
        }
        if ($style->kind === 'presenter' && !$this->image) {
            $problems[] = 'Falta la imagen del presentador.';
        }

        return $problems;
    }
}
