<?php namespace Aero\Office\Classes;

use Aero\Office\Models\Customer;

/**
 * Espejo del cliente en Aero.Crm (integración blanda: sin CRM no pasa nada).
 * Enlaza por correo/teléfono dentro del mismo tenant o crea el contacto.
 */
class CrmLink
{
    /** Los sembradores de demostración lo apagan para no ensuciar el CRM real. */
    public static bool $disabled = false;

    public static function available(): bool
    {
        return !self::$disabled && class_exists(\Aero\Crm\Models\Contact::class) && \Schema::hasTable('aero_crm_contacts');
    }

    public static function sync(Customer $c): void
    {
        if (!self::available() || $c->crm_contact_id || !$c->tenant_id) {
            return;
        }

        try {
            $q = \Aero\Crm\Models\Contact::where('tenant_id', $c->tenant_id)->where(function ($w) use ($c) {
                if ($c->email) {
                    $w->orWhere('email', $c->email);
                }
                if ($c->phone) {
                    $w->orWhere('phone', $c->phone);
                }
            });
            $contact = ($c->email || $c->phone) ? $q->first() : null;

            if (!$contact) {
                $parts = preg_split('/\s+/', trim($c->name), 2);
                $contact = \Aero\Crm\Models\Contact::create([
                    'tenant_id'  => $c->tenant_id,
                    'first_name' => $parts[0],
                    'last_name'  => $parts[1] ?? null,
                    'email'      => $c->email,
                    'phone'      => $c->phone,
                    'source'     => 'office',
                ]);
            }

            $c->newQuery()->whereKey($c->id)->update(['crm_contact_id' => $contact->id]);
            $c->crm_contact_id = $contact->id;
        } catch (\Throwable $e) {
            \Log::warning('[office] No se pudo enlazar con el CRM: ' . $e->getMessage());
        }
    }
}
