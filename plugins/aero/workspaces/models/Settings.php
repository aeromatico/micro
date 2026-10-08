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
}
