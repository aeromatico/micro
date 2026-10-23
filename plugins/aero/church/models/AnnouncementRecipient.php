<?php namespace Aero\Church\Models;

use Model;

/** A quién se envió un comunicado y cómo salió (historial). */
class AnnouncementRecipient extends Model
{
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_announcement_recipients';

    public $fillable = ['tenant_id', 'announcement_id', 'member_id', 'phone', 'status', 'error'];

    public $belongsTo = [
        'announcement' => [Announcement::class, 'key' => 'announcement_id'],
        'member'       => [Member::class, 'key' => 'member_id'],
    ];
}
