<?php namespace Aero\Workspaces\Models;

use Model;

/**
 * Ajustes globales de Workspaces. Sin secretos (SettingsModel no cifra).
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_workspaces_settings';

    public $settingsFields = 'fields.yaml';

    /** Tipos de modelo del catálogo. Solo `code` y `text` conversan con herramientas (los agentes); el resto es catálogo para la generación de medios. */
    public const KINDS = [
        'code'  => 'Código',
        'text'  => 'Texto / chat',
        'image' => 'Imagen',
        'audio' => 'Audio',
        'video' => 'Video',
    ];

    public const CHAT_KINDS = ['code', 'text'];

    /**
     * ¿Se cobran puntos (créditos) al contratar y al enviar encargos? Apagado por
     * defecto: mientras la ejecución sea simulada, un encargo no entrega nada.
     */
    public static function chargeEnabled(): bool
    {
        return (bool) self::get('charge_enabled', false);
    }

    /** Código del tipo de crédito que hace de «puntos» (azul, rojo…). */
    public static function creditTypeCode(): string
    {
        return trim((string) self::get('credit_type', 'azul')) ?: 'azul';
    }

    /** Conector de IA (chat con herramientas) que usan los agentes reales; vacío = el del propio agente o el primero activo. */
    public static function agentConnectorId(): ?int
    {
        return ((int) self::get('agent_connector_id')) ?: null;
    }

    /** Modelo a pedir al conector; vacío = el predeterminado del conector. */
    public static function agentModel(): ?string
    {
        return trim((string) self::get('agent_model')) ?: null;
    }

    /** Turnos de conversación con agentes por tenant y hora (frena costos de IA). */
    public static function agentTurnsPerHour(): int
    {
        return max(1, (int) self::get('agent_turns_per_hour', 60));
    }

    /**
     * Catálogo de modelos especializados (el repetidor de Ajustes), solo los
     * activos y con modelo, indexados por código.
     *
     * @return array<string, array{code:string,label:string,kind:string,model:string,connector_id:?int}>
     */
    public static function models(): array
    {
        $out = [];

        foreach ((array) self::get('models', []) as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $model = trim((string) ($row['model'] ?? ''));

            if ($code === '' || $model === '' || (array_key_exists('is_active', $row) && !$row['is_active'])) {
                continue;
            }

            $kind = array_key_exists($row['kind'] ?? '', self::KINDS) ? $row['kind'] : 'text';

            $out[$code] = [
                'code'         => $code,
                'label'        => trim((string) ($row['label'] ?? '')) ?: $code,
                'kind'         => $kind,
                'model'        => $model,
                'connector_id' => ((int) ($row['connector_id'] ?? 0)) ?: null,
            ];
        }

        return $out;
    }

    public static function modelProfile(?string $code): ?array
    {
        return $code ? (static::models()[$code] ?? null) : null;
    }

    /** Primer modelo activo de un tipo (para la generación de imagen, audio y video). */
    public static function modelOfKind(string $kind): ?array
    {
        foreach (static::models() as $profile) {
            if ($profile['kind'] === $kind) {
                return $profile;
            }
        }

        return null;
    }

    /** Conectores de IA para los desplegables de Ajustes. */
    public function getAgentConnectorIdOptions(): array
    {
        return $this->aiConnectors();
    }

    /** Conector propio de un modelo del catálogo (vacío = el de arriba). */
    public function getConnectorIdOptions(): array
    {
        return ['' => '(el conector de los agentes)'] + $this->aiConnectors();
    }

    protected function aiConnectors(): array
    {
        if (!class_exists(\Aero\Connector\Models\Connector::class)) {
            return [];
        }

        return \Aero\Connector\Models\Connector::whereIn('type', ['ai_openai_compatible', 'ai_anthropic'])->orderBy('name')->get()
            ->mapWithKeys(fn ($c) => [$c->id => "#{$c->id} {$c->name}" . ($c->is_enabled ? '' : ' (desactivado)')])->all();
    }
}
