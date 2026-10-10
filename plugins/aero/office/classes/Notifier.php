<?php namespace Aero\Office\Classes;

use Aero\Office\Models\Booking;
use Aero\Office\Models\OfficeSettings;

/**
 * Único punto por el que Oficina avisa de lo que pasa con una reserva: dispara
 * eventos office.booking.* en Aero.Notify (gateway), que entrega por WhatsApp
 * (Aero.Hello) o correo según las reglas. Integración blanda y NUNCA lanza:
 * un fallo de aviso no puede romper una reserva.
 *
 * Al cliente le llega solo lo que él no hizo: lo que inicia el cliente en el
 * portal avisa al negocio; lo que hace el personal avisa al cliente.
 */
class Notifier
{
    /** Los sembradores de demostración lo activan para no escribir a teléfonos de prueba. */
    public static bool $muted = false;

    public static function available(): bool
    {
        return class_exists(\Aero\Notify\Classes\Notify::class);
    }

    /** @param array $extra variables adicionales (p. ej. reason) */
    public static function fire(string $event, Booking $b, array $extra = []): bool
    {
        if (self::$muted || !self::available()) {
            return false;
        }

        try {
            $settings = OfficeSettings::forTenant($b->tenant_id);
            $toCustomer = !str_contains($event, '_by_customer') && $event !== 'office.booking.created';
            if ($toCustomer && !($settings->notify_customers ?? true)) {
                return false;
            }

            \Aero\Notify\Classes\Notify::fire($event, self::context($b, $settings) + $extra, [
                'tenant_id' => (int) $b->tenant_id,
                'actor'     => [
                    'name'  => $b->customer?->name,
                    'email' => $b->customer?->email,
                    'phone' => $b->customer?->phone,
                ],
                // Una reprogramación a otra hora es un aviso distinto; el mismo no se repite.
                'dedup_key' => "office:{$b->id}:{$event}:" . $b->starts_at->timestamp . ':' . $b->status,
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::error("Aero.Office: no se pudo notificar {$event} de la reserva {$b->id}: " . $e->getMessage());

            return false;
        }
    }

    public static function context(Booking $b, ?OfficeSettings $settings = null): array
    {
        $b->loadMissing(['customer', 'branch', 'worker']);
        $settings ??= OfficeSettings::forTenant($b->tenant_id);
        $tenant = self::tenant($b->tenant_id);

        return [
            'code'           => $b->code,
            'customer_name'  => (string) ($b->customer?->name ?? ''),
            'customer_phone' => (string) ($b->customer?->phone ?? ''),
            'service_name'   => $b->service_name,
            'worker_name'    => (string) ($b->worker?->name ?: $b->worker_name),
            'branch_name'    => (string) ($b->branch?->name ?? ''),
            'branch_address' => (string) ($b->branch?->address ?? ''),
            'starts_at'      => $b->starts_at->locale('es')->translatedFormat('l d/m \a \l\a\s H:i'),
            'business_name'  => (string) ($settings->business_name ?: $tenant?->name),
            'url'            => self::manageUrl($b, $tenant),
            'reason'         => (string) ($b->cancel_reason ?? ''),
            'tenant_name'    => $tenant?->name,
        ];
    }

    protected static function tenant($id)
    {
        try {
            return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($id) : null;
        } catch (\Throwable $e) {
            return null; // Sites no disponible: el aviso sale sin nombre de tenant ni enlace
        }
    }

    public static function manageUrl(Booking $b, $tenant = null): string
    {
        $tenant ??= self::tenant($b->tenant_id);
        if (!$tenant || !OfficeSettings::isPublic($b->tenant_id)) {
            return '';
        }

        return 'https://' . $tenant->primary_domain . '/reservas/' . $b->manage_token;
    }

    /** Recordatorios: citas confirmadas dentro de la ventana, reservadas con antelación suficiente. */
    public static function sendReminders(): int
    {
        if (self::$muted || !self::available()) {
            return 0;
        }

        $sent = 0;
        $tenantIds = Booking::where('status', 'confirmed')->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDays(7))
            ->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tid) {
            $s = OfficeSettings::forTenant((int) $tid);
            $hours = (int) ($s->reminder_hours ?? 24);
            if (!($s->enabled ?? true) || !($s->notify_customers ?? true) || $hours <= 0) {
                continue;
            }

            Booking::where('tenant_id', $tid)->where('status', 'confirmed')->whereNull('reminder_sent_at')
                ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours($hours))
                ->with(['customer', 'branch', 'worker'])->orderBy('starts_at')->limit(200)->get()
                ->each(function (Booking $b) use ($hours, &$sent) {
                    // Reservada dentro de la ventana: la confirmación ya fue el aviso.
                    if ($b->created_at && $b->created_at->gt($b->starts_at->copy()->subHours($hours))) {
                        $b->forceFill(['reminder_sent_at' => now()])->saveQuietly();

                        return;
                    }
                    if (self::fire('office.booking.reminder', $b)) {
                        $b->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                        $sent++;
                    }
                });
        }

        return $sent;
    }
}
