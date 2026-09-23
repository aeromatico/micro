<?php namespace Aero\Livechat\Models;

use Aero\Sites\Models\Tenant;
use Model;
use Str;

/** Visitante anónimo (o identificado por nombre/correo) de un widget. */
class Contact extends Model
{
    public $table = 'aero_livechat_contacts';

    public $fillable = ['tenant_id', 'name', 'email', 'phone', 'visitor_token', 'last_seen_at'];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public $hasMany = [
        'conversations' => [Conversation::class],
    ];

    protected $dates = ['last_seen_at'];

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
}
