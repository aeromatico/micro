<?php namespace Aero\Livechat\Models;

use Aero\Sites\Models\Tenant;
use Model;
use Str;

/** Visitante anónimo (o identificado por nombre/correo) de un widget. */
class Contact extends Model
{
    public $table = 'aero_livechat_contacts';

    public $fillable = ['tenant_id', 'name', 'email', 'phone', 'visitor_token', 'last_seen_at', 'is_banned', 'banned_until'];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public $hasMany = [
        'conversations' => [Conversation::class],
    ];

    protected $dates = ['last_seen_at', 'banned_until'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $contact) {
            $contact->visitor_token = $contact->visitor_token ?: Str::random(40);
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->email ?: "Visitante #{$this->id}");
    }

    /** `banned_until` null con `is_banned` true es un ban permanente; con fecha, vence solo. */
    public function isBanned(): bool
    {
        return (bool) $this->is_banned && (!$this->banned_until || $this->banned_until->isFuture());
    }

    public function ban(?int $hours): void
    {
        $this->is_banned = true;
        $this->banned_until = $hours ? now()->addHours($hours) : null;
        $this->save();
    }

    public function unban(): void
    {
        $this->is_banned = false;
        $this->banned_until = null;
        $this->save();
    }
}
