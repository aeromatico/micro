<?php namespace Aero\Chatbots\Models;

use Model;

/**
 * Catálogo global (solo superadmin, menú "Configuración") de qué modelos de
 * qué Connector de IA están habilitados para que los bots elijan, y cuánto
 * cobrar en créditos por cada respuesta con ese modelo. Los bots (tenant
 * admins incluidos) solo eligen entre estos, nunca definen su propio costo.
 */
class AiModel extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_chatbots_ai_models';

    public $fillable = [
        'connector_id', 'model_id', 'label', 'credit_cost', 'credit_type_id', 'is_active',
    ];

    public $rules = [
        'connector_id' => 'required',
        'model_id'     => 'required',
        'label'        => 'required',
    ];

    public $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $belongsTo = [
        'connector' => [\Aero\Connector\Models\Connector::class],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getConnectorLabelAttribute(): ?string
    {
        return $this->connector?->name;
    }

    /**
     * Solo conectores de categoría "ai" (Aero.Connector es dependencia
     * opcional del plugin, ver Aero\Chatbots\Classes\ChatbotEngine).
     */
    public function getConnectorIdOptions(): array
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return [];
        }

        return \Aero\Connector\Models\Connector::orderBy('name')
            ->get()
            ->filter(fn ($connector) => (\Aero\Connector\Classes\TypeRegistry::find($connector->type)['category'] ?? null) === 'ai')
            ->mapWithKeys(fn ($connector) => [$connector->id => "{$connector->name} ({$connector->type_label})"])
            ->all();
    }

    /**
     * Vacío si Aero.Credits no está instalado: el campo simplemente no
     * tiene opciones y `credit_cost` queda sin efecto.
     */
    public function getCreditTypeIdOptions(): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        return \Aero\Credits\Models\CreditType::active()->pluck('label', 'id')->all();
    }
}
