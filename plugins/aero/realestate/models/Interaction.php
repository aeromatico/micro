<?php namespace Aero\Realestate\Models;

use Model;

/** Historial comercial de un cliente: notas, llamadas, WhatsApp, visitas, cambios de etapa y cierres. */
class Interaction extends Model
{
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_interactions';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'client_id', 'lead_id', 'type', 'body', 'user_id', 'created_at'];

    public $dates = ['created_at'];

    public $belongsTo = [
        'client' => [Client::class, 'key' => 'client_id'],
        'lead'   => [Lead::class, 'key' => 'lead_id'],
    ];

    public static array $types = [
        'note' => 'Nota', 'call' => 'Llamada', 'whatsapp' => 'WhatsApp', 'email' => 'Correo',
        'visit' => 'Visita', 'stage' => 'Etapa', 'deal' => 'Cierre',
    ];

    public static function log(int $clientId, ?int $leadId, ?int $tenantId, string $type, string $body): self
    {
        return static::create([
            'tenant_id' => $tenantId, 'client_id' => $clientId, 'lead_id' => $leadId, 'type' => $type, 'body' => $body,
            'user_id' => \BackendAuth::getUser()?->id, 'created_at' => now(),
        ]);
    }

    public function getTypeNameAttribute(): string
    {
        return self::$types[$this->type] ?? (string) $this->type;
    }
}
