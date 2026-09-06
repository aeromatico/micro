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
        'bot_id', 'trigger_keywords', 'response_text', 'priority', 'is_active',
    ];

    // 'array' cast serializa a JSON antes de validar, así que la regla
    // 'required' vería siempre un string no-vacío. jsonable no tiene ese
    // problema porque conserva el valor real hasta el guardado.
    public $jsonable = ['trigger_keywords'];

    public $rules = [
        'trigger_keywords' => 'required',
        'response_text'    => 'required',
    ];

    public $belongsTo = [
        'bot' => [Bot::class],
    ];

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
