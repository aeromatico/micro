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

    public $fillable = ['tenant_id', 'name', 'welcome_message', 'color', 'is_active'];

    public $attributes = ['color' => '#4f46e5', 'is_active' => true];

    public $rules = [
        'tenant_id' => 'nullable|exists:aero_sites_tenants,id',
        'name'      => 'required|max:255',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
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

        return sprintf(
            '<script src="%s/plugins/aero/livechat/assets/js/widget.js" data-livechat-key="%s" data-livechat-base="%s" async></script>',
            $base,
            $this->widget_key,
            $base
        );
    }
}
