<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/**
 * Definición de cuestionario como DATOS (preguntas con opciones y puntaje, e
 * interpretación por rangos). El puntaje lo suma el código; nada clínico está fijo.
 */
class Questionnaire extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_questionnaires';

    public $fillable = ['tenant_id', 'name', 'description', 'questions', 'ranges', 'is_active'];

    public $jsonable = ['questions', 'ranges'];

    public $casts = ['is_active' => 'boolean'];

    public $rules = ['name' => 'required|string|max:255'];

    public function beforeValidate(): void
    {
        $questions = [];
        $keys = [];
        foreach ((array) $this->questions as $i => $q) {
            $text = trim((string) ($q['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $options = [];
            foreach ((array) ($q['options'] ?? []) as $o) {
                $label = trim((string) ($o['label'] ?? ''));
                if ($label === '' || !isset($o['score']) || !is_numeric($o['score'])) {
                    continue;
                }
                $options[] = ['label' => $label, 'score' => (float) $o['score']];
            }
            if (count($options) < 2) {
                throw new \ApplicationException("La pregunta «{$text}» necesita al menos dos opciones con puntaje.");
            }
            $key = 'q' . (count($questions) + 1);
            $keys[] = $key;
            $questions[] = ['key' => $key, 'text' => $text, 'options' => $options];
        }
        if (!$questions) {
            throw new \ApplicationException('El cuestionario necesita al menos una pregunta.');
        }
        $this->questions = $questions;

        $ranges = [];
        foreach ((array) $this->ranges as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '' || !is_numeric($r['min'] ?? null) || !is_numeric($r['max'] ?? null)) {
                continue;
            }
            if ((float) $r['min'] > (float) $r['max']) {
                throw new \ApplicationException("El rango «{$label}» tiene el mínimo mayor que el máximo.");
            }
            $ranges[] = ['min' => (float) $r['min'], 'max' => (float) $r['max'], 'label' => $label];
        }
        usort($ranges, fn ($a, $b) => $a['min'] <=> $b['min']);
        $this->ranges = $ranges;
    }

    /**
     * Puntaje sumado por código y su interpretación.
     * @param array<string,int> $answers pregunta => índice de la opción elegida
     * @return array{score:float, interpretation:?string, answers:array}
     */
    public static function evaluate(array $definition, array $answers): array
    {
        $score = 0.0;
        $clean = [];

        foreach ((array) $definition['questions'] as $q) {
            $idx = $answers[$q['key']] ?? null;
            if ($idx === null || $idx === '' || !is_numeric($idx) || !isset($q['options'][(int) $idx])) {
                throw new \ApplicationException('Responda todas las preguntas: falta «' . $q['text'] . '».');
            }
            $score += (float) $q['options'][(int) $idx]['score'];
            $clean[$q['key']] = (int) $idx;
        }

        $label = null;
        foreach ((array) ($definition['ranges'] ?? []) as $r) {
            if ($score >= $r['min'] && $score <= $r['max']) {
                $label = $r['label'];
                break;
            }
        }

        return ['score' => round($score, 2), 'interpretation' => $label, 'answers' => $clean];
    }
}
