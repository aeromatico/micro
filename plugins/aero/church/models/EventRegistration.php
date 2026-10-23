<?php namespace Aero\Church\Models;

use Model;

/** Inscripción de un miembro (o invitado) a un evento. `token` es el contenido de su QR personal. */
class EventRegistration extends Model
{
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_event_registrations';

    public $fillable = ['tenant_id', 'event_id', 'member_id', 'guest_name', 'guest_phone', 'status', 'token', 'checked_in_at', 'reminder_sent_at'];

    public $dates = ['checked_in_at', 'reminder_sent_at'];

    public $belongsTo = [
        'event'  => [ChurchEvent::class, 'key' => 'event_id'],
        'member' => [Member::class, 'key' => 'member_id'],
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->member?->full_name ?: ($this->guest_name ?: '—');
    }

    /** QR (SVG) con el token; lo escanea quien controla el ingreso. */
    public function qrSvg(int $size = 120): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );

        return (new \BaconQrCode\Writer($renderer))->writeString($this->token);
    }
}
