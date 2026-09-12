<?php namespace Aero\Chatbots\Models;

use Aero\Hello\Models\Account;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Model;

/**
 * Un chatbot de reglas simples atado a una única cuenta de Aero.Hello
 * (número/perfil social). Un tenant puede tener varios bots si tiene varias
 * cuentas conectadas.
 */
class Bot extends Model
{
    use \October\Rain\Database\Traits\Validation;
    // Los <select> de un FormField solo consultan métodos del modelo (ver
    // FormField::getOptionsFromModelAsDefault), no del controller, así que
    // el filtrado por tenant vive aquí en vez de en Bots.
    use ResolvesCurrentTenant;

    public $table = 'aero_chatbots_bots';

    public $fillable = [
        'tenant_id', 'account_id', 'name', 'is_active', 'fallback_message', 'handoff_minutes',
        'reply_mode', 'ai_connector_id', 'ai_model', 'ai_system_prompt', 'ai_tool_categories',
    ];

    protected $jsonable = ['ai_tool_categories'];

    public $attributes = [
        'reply_mode' => 'autoresponder',
    ];

    public $rules = [
        // account_id sigue siendo obligatorio: es la cuenta de Hello a la que
        // se ata el bot, hace falta en cualquier modo (autoresponder o IA) y
        // la columna es NOT NULL en la BD — no tiene sentido relajarlo.
        'account_id'      => 'required|unique:aero_chatbots_bots',
        // name/handoff_minutes ya no son obligatorios: alguien que va
        // directo a modo IA no debería trabarse llenando campos que son del
        // autorespondedor clásico. beforeValidate() les pone un default
        // sensato si quedan vacíos, para no mandar '' a columnas NOT NULL.
        'name'            => 'nullable|max:255',
        'handoff_minutes' => 'nullable|integer|min:0|max:1440',
    ];

    public $belongsTo = [
        'account'     => [\Aero\Hello\Models\Account::class],
        'tenant'      => [\Aero\Sites\Models\Tenant::class],
        'aiConnector' => [\Aero\Connector\Models\Connector::class, 'key' => 'ai_connector_id'],
    ];

    public $hasMany = [
        'rules' => [Rule::class, 'delete' => true],
    ];

    /**
     * `name`/`handoff_minutes` dejaron de ser obligatorios en el form, pero
     * las columnas son NOT NULL — sin esto, dejarlos vacíos tiraría el
     * mismo error de "integer inválido"/columna nula que ya vimos con
     * puertos y costos de créditos en aero/connector.
     */
    public function beforeValidate()
    {
        if (!$this->name) {
            $this->name = $this->account?->label ? "Bot de {$this->account->label}" : 'Bot sin nombre';
        }

        if ($this->handoff_minutes === null || $this->handoff_minutes === '') {
            $this->handoff_minutes = 15;
        }

        // "Desactivar" en el selector de modo es la única fuente de verdad
        // para is_active — evita que quede un bot con reply_mode='ai' pero
        // is_active=false (o viceversa) por tocar el switch de la lista sin
        // pasar por acá. ChatbotEngine::handle() sigue filtrando por
        // is_active (Bot::active()), así que esto es lo único que hace falta
        // para que "Desactivar" corte las respuestas automáticas del todo.
        $this->is_active = $this->reply_mode !== 'disabled';
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Cuentas de Hello disponibles para el <select> del form: si hay un
     * tenant en contexto, solo las suyas (vía su Profile); el superadmin sin
     * tenant propio ve todas.
     */
    public function getAccountIdOptions(): array
    {
        $query = Account::orderBy('label');

        if ($tenantId = $this->getCurrentTenantId()) {
            $query->whereHas('profile', fn ($q) => $q->where('tenant_id', $tenantId));
        }

        return $query->get()
            ->mapWithKeys(fn ($account) => [$account->id => "{$account->label} ({$account->platform})"])
            ->all();
    }

    public function getTenantIdOptions(): array
    {
        return Tenant::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Conectores de IA disponibles (Aero.Connector es dependencia opcional):
     * solo los habilitados cuyo tipo esté registrado con category "ai".
     */
    public function getAiConnectorIdOptions(): array
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return [];
        }

        return \Aero\Connector\Models\Connector::where('is_enabled', true)
            ->get()
            ->filter(fn ($connector) => (\Aero\Connector\Classes\TypeRegistry::find($connector->type)['category'] ?? null) === 'ai')
            ->mapWithKeys(fn ($connector) => [$connector->id => "{$connector->name} ({$connector->type_label})"])
            ->all();
    }

    /**
     * Modelos habilitados para el connector elegido, del catálogo global que
     * administra el superadmin en "Configuración" > "Modelos de IA" (ver
     * Aero\Chatbots\Models\AiModel). Vacío si todavía no se eligió connector.
     */
    public function getAiModelOptions(): array
    {
        if (!$this->ai_connector_id) {
            return [];
        }

        return AiModel::active()
            ->where('connector_id', $this->ai_connector_id)
            ->pluck('label', 'model_id')
            ->all();
    }

    /**
     * Categorías de "AI tools" (Súper IA) declaradas por cualquier plugin
     * vía el evento `aero.chatbots.registerAiTools` — hoy solo `site` y
     * `shop` (Aero.Sites y Aero.Shop), pero no hace falta tocar este método
     * ni migrar la BD para agregar una nueva: alcanza con que el plugin
     * dueño del dato la declare (ver Aero\Chatbots\Classes\AiToolRegistry).
     */
    public function getAiToolCategoriesOptions(): array
    {
        $labels = [
            'site' => 'Sitio web / landing',
            'shop' => 'Tienda',
        ];

        $options = [];

        foreach (\Aero\Chatbots\Classes\AiToolRegistry::categories() as $category) {
            $options[$category] = $labels[$category] ?? ucfirst($category);
        }

        return $options;
    }
}
