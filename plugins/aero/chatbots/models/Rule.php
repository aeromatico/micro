<?php namespace Aero\Chatbots\Models;

use Model;

/**
 * Una regla de palabra(s) clave -> respuesta. La primera regla activa (por
 * `priority` descendente) cuyas keywords aparezcan en el mensaje entrante
 * gana; si ninguna matchea, el bot usa `fallback_message`.
 */
class Rule extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_chatbots_rules';

    public $fillable = [
        'bot_id', 'trigger_keywords', 'quick_reply_id', 'response_text', 'priority', 'is_active',
    ];

    // 'array' cast serializa a JSON antes de validar, así que la regla
    // 'required' vería siempre un string no-vacío. jsonable no tiene ese
    // problema porque conserva el valor real hasta el guardado.
    public $jsonable = ['trigger_keywords'];

    public $rules = [
        'trigger_keywords' => 'required',
        'response_text'    => 'required_without:quick_reply_id',
    ];

    public $belongsTo = [
        'bot' => [Bot::class],
    ];

    /** El desplegable manda '' cuando no se elige nada; la columna es entera y admite NULL. */
    public function setQuickReplyIdAttribute($value): void
    {
        $this->attributes['quick_reply_id'] = $value === '' || $value === null ? null : (int) $value;
    }

    public function getQuickReplyAttribute()
    {
        return $this->quick_reply_id && class_exists(\Aero\Crm\Models\QuickReply::class)
            ? \Aero\Crm\Models\QuickReply::find($this->quick_reply_id)
            : null;
    }

    /** Respuestas rápidas del cliente (o de la plataforma si no hay tenant) para elegir en el formulario. */
    public function getQuickReplyIdOptions(): array
    {
        if (!class_exists(\Aero\Crm\Models\QuickReply::class)) {
            return [];
        }

        return \Aero\Crm\Models\QuickReply::inScope(\Aero\Crm\Classes\TenantUsers::currentTenantId())
            ->active()->orderBy('shortcut')->get()
            ->mapWithKeys(fn ($q) => [$q->id => '/' . $q->shortcut . ' — ' . $q->title])->all();
    }

    /** Texto para listas y registros: el de la respuesta vinculada, o el propio. */
    public function getReplyPreviewAttribute(): string
    {
        $q = $this->quick_reply;

        return $q ? '↗ /' . $q->shortcut . ' — ' . $q->title : (string) $this->response_text;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * true si alguna de las keywords de la regla aparece dentro del texto
     * (case-insensitive, substring simple — nada de regex en v1).
     */
    public function matches(string $body): bool
    {
        $haystack = mb_strtolower($body);

        foreach ((array) $this->trigger_keywords as $keyword) {
            $keyword = mb_strtolower(trim((string) $keyword));

            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
