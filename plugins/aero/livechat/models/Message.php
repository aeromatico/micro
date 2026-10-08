<?php namespace Aero\Livechat\Models;

use Backend\Models\User;
use Model;

class Message extends Model
{
    public const CONTACT = 'contact', AGENT = 'agent', SYSTEM = 'system';

    public $table = 'aero_livechat_messages';

    public $fillable = [
        'conversation_id', 'sender_type', 'sender_id', 'body', 'telegram_message_id',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'attachment_token',
    ];

    public $belongsTo = [
        'conversation' => [Conversation::class],
        'agent'        => [User::class, 'key' => 'sender_id'],
    ];

    public function hasAttachment(): bool
    {
        return (bool) $this->attachment_token;
    }

    public function isImageAttachment(): bool
    {
        return $this->hasAttachment() && str_starts_with((string) $this->attachment_mime, 'image/');
    }

    public function isAudioAttachment(): bool
    {
        return $this->hasAttachment() && str_starts_with((string) $this->attachment_mime, 'audio/');
    }

    public function isVideoAttachment(): bool
    {
        return $this->hasAttachment() && str_starts_with((string) $this->attachment_mime, 'video/');
    }

    /** URL pública (sin auth — el token de 32+ chars es la única llave) para <img>/descarga, cross-domain. */
    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_token
            ? url('api/v1/livechat/attachments/' . $this->attachment_token)
            : null;
    }
}
