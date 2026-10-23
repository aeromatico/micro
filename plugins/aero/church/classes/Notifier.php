<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Prayer;
use Aero\Church\Models\Visit;

/**
 * Avisos internos vía Aero.Notify (integración blanda). NUNCA lanza ni lleva contenido pastoral: de una
 * petición de oración solo avisa que existe y si pide contacto.
 */
class Notifier
{
    public static bool $muted = false;

    protected static function available(): bool
    {
        return !self::$muted && !app()->environment('testing') && class_exists(\Aero\Notify\Classes\Notify::class);
    }

    protected static function url(?int $tenantId, string $path): string
    {
        $tenant = $tenantId && class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($tenantId) : null;

        return $tenant ? $tenant->backendUrl('backend/aero/church/' . $path) : \Backend::url('aero/church/' . $path);
    }

    public static function visitRegistered(Visit $v): void
    {
        if (!self::available()) {
            return;
        }
        try {
            \Aero\Notify\Classes\Notify::fire('church.visit.registered', [
                'visit_name' => $v->name, 'source' => $v->source === 'qr' ? 'formulario QR' : ($v->source === 'manual' ? 'registro manual' : 'API'),
                'url' => self::url($v->tenant_id, 'visits/update/' . $v->id),
            ], ['tenant_id' => $v->tenant_id, 'dedup_key' => "church:visit:{$v->id}"]);
        } catch (\Throwable $e) {
            \Log::warning('[church] aviso de visitante no enviado: ' . $e->getMessage());
        }
    }

    public static function prayerRequested(Prayer $p): void
    {
        if (!self::available()) {
            return;
        }
        try {
            \Aero\Notify\Classes\Notify::fire('church.prayer.requested', [
                'wants_contact' => $p->wants_contact ? 'sí' : 'no', 'url' => self::url($p->tenant_id, 'prayers/update/' . $p->id),
            ], ['tenant_id' => $p->tenant_id, 'dedup_key' => "church:prayer:{$p->id}"]);
        } catch (\Throwable $e) {
            \Log::warning('[church] aviso de oración no enviado: ' . $e->getMessage());
        }
    }
}
