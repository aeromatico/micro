<?php namespace Aero\Livechat\Models;

use Aero\Livechat\Classes\TenantScope;
use Aero\Sites\Models\Tenant;
use Backend\Models\User;
use Model;

class Conversation extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public const OPEN = 'open', RESOLVED = 'resolved';

    public $table = 'aero_livechat_conversations';

    public $fillable = ['tenant_id', 'inbox_id', 'contact_id', 'status', 'assigned_to', 'page_url'];

    public $attributes = ['status' => self::OPEN];

    public $rules = [
        'status' => 'in:open,resolved',
    ];

    protected $dates = ['last_message_at'];

    public $belongsTo = [
        'tenant'   => [Tenant::class],
        'inbox'    => [Inbox::class],
        'contact'  => [Contact::class],
        'assignee' => [User::class, 'key' => 'assigned_to'],
    ];

    public $hasMany = [
        'messages' => [Message::class, 'order' => 'created_at asc'],
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $c) {
            if ($c->assigned_to === '' || $c->assigned_to === '0') {
                $c->assigned_to = null;
            }
        });
    }

    public static function statusOptions(): array
    {
        return [self::OPEN => 'Abierta', self::RESOLVED => 'Resuelta'];
    }

    public function getStatusOptions(): array
    {
        return static::statusOptions();
    }

    public function getAssignedToOptions(): array
    {
        return TenantScope::agentOptions(
            $this->exists ? $this->tenant_id : TenantScope::currentTenantId(),
            $this->assigned_to
        );
    }

    /** Último mensaje, para la vista previa en el listado. */
    public function getLastMessageAttribute(): ?Message
    {
        return $this->messages()->orderBy('id', 'desc')->first();
    }

    public function scopeInScope($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');
    }

    public function scopeOpenFor($query, int $inboxId, int $contactId)
    {
        return $query->where('inbox_id', $inboxId)->where('contact_id', $contactId)->latest('id');
    }
}
