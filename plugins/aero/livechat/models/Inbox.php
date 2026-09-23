<?php namespace Aero\Livechat\Models;

use Aero\Sites\Models\Tenant;
use Model;
use Str;

/**
 * Un inbox = un widget embebible. tenant_id NULL = mesa de soporte de la
 * plataforma; con valor = inbox propio del tenant. widget_key es público (va
 * en el <script> del sitio del tenant), nunca un secreto.
 */
class Inbox extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_livechat_inboxes';

    public $fillable = [
        'tenant_id', 'name', 'welcome_message', 'color', 'is_active',
        'telegram_connector_id', 'telegram_chat_id',
    ];

    public $attributes = ['color' => '#4f46e5', 'is_active' => true];

    public $rules = [
        'tenant_id'              => 'nullable|exists:aero_sites_tenants,id',
        'name'                   => 'required|max:255',
        'telegram_connector_id'  => 'nullable|exists:aero_connector_connectors,id',
    ];

    public $belongsTo = [
        'tenant'            => [Tenant::class],
        'telegramConnector' => [\Aero\Connector\Models\Connector::class, 'key' => 'telegram_connector_id'],
    ];

    public $hasMany = [
        'conversations' => [Conversation::class],
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $inbox) {
            $inbox->widget_key = $inbox->widget_key ?: (string) Str::uuid();
        });

        static::saving(function (self $inbox) {
            if ($inbox->telegram_connector_id === '' || $inbox->telegram_connector_id === '0') {
                $inbox->telegram_connector_id = null;
            }
        });
    }

    public function getTelegramConnectorIdOptions(): array
    {
        return \Aero\Connector\Models\Connector::where('type', 'telegram')
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function scopeInScope($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');
    }

    public function getConversationsCountAttribute(): int
    {
        return $this->conversations()->count();
    }

    /** Snippet listo para pegar en el <head> del sitio del tenant. */
    public function getEmbedSnippetAttribute(): string
    {
        $base = rtrim(\Config::get('app.url'), '/');
        // Cloudflare cachea el asset 7 días (immutable en la práctica): sin
        // ?v= un cambio en widget.js queda invisible para todos los tenants
        // hasta que expire el cache. Ver feedback_cloudflare_asset_cache_busting.
        $version = filemtime(base_path('plugins/aero/livechat/assets/js/widget.js')) ?: time();

        return sprintf(
            '<script src="%s/plugins/aero/livechat/assets/js/widget.js?v=%s" data-livechat-key="%s" data-livechat-base="%s" async></script>',
            $base,
            $version,
            $this->widget_key,
            $base
        );
    }
}
