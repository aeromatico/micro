<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\GuardsSignedNote;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Respuestas a un cuestionario con la foto de su definición. Se bloquean con la nota. */
class QuestionnaireAnswer extends Model
{
    use TenantOwned;
    use GuardsSignedNote;

    public $table = 'aero_medical_questionnaire_answers';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'questionnaire_id', 'questionnaire_name', 'definition', 'answers', 'score', 'interpretation', 'user_id', 'answered_at'];

    public $jsonable = ['definition'];

    public $casts = ['answers' => 'encrypted:array'];

    protected $dates = ['answered_at'];

    public function beforeSave(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }

    public function beforeDelete(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }

    /** Respuestas legibles: pregunta → opción elegida (puntaje). */
    public function readable(): array
    {
        $out = [];
        foreach ((array) ($this->definition['questions'] ?? []) as $q) {
            $i = ((array) $this->answers)[$q['key']] ?? null;
            $o = $i !== null ? ($q['options'][$i] ?? null) : null;
            $out[] = ['question' => $q['text'], 'answer' => $o['label'] ?? '—', 'score' => $o['score'] ?? null];
        }

        return $out;
    }
}
