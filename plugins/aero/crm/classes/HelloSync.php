<?php namespace Aero\Crm\Classes;

use Aero\Crm\Models\Contact as CrmContact;
use Aero\Crm\Models\CrmSettings;
use Aero\Hello\Classes\PhoneNumber;
use Aero\Hello\Models\Contact as HelloContact;
use Aero\Hello\Models\ContactIdentity;
use Illuminate\Support\Facades\Log;

/**
 * Sincronía bidireccional entre los contactos de Hello (chat) y los del CRM.
 *
 * - CRM → Hello: ya existía (Contact::syncHelloContact): el contacto del CRM
 *   tiene un espejo en Hello con su nombre y su número de WhatsApp.
 * - Hello → CRM (esto): cualquier contacto de WhatsApp que aparece en el chat
 *   (mensaje entrante, envío desde Redactar/API) se crea o se enlaza en el
 *   CRM del tenant, con el nombre de perfil de WhatsApp si lo hay. Después
 *   nombre y teléfono se mantienen iguales desde cualquiera de los dos lados.
 *
 * El enlace es `crm_contacts.hello_contact_id`. Solo aplica a tenants con el
 * CRM activado. Un contacto con nombre real nunca se pisa con un
 * marcador (número) ni con el nombre de WhatsApp: gana lo que alguien escribió.
 */
class HelloSync
{
    protected static bool $busy = false;

    public static function available(): bool
    {
        return class_exists(HelloContact::class);
    }

    public static function crmEnabled(?int $tenantId): bool
    {
        return $tenantId && CrmSettings::where('tenant_id', $tenantId)->value('is_enabled');
    }

    /**
     * Ejecuta $fn sin que los listeners de modelo de Hello/CRM reaccionen a
     * lo que $fn mismo escribe (evita bucles entre los dos sentidos).
     */
    public static function quietly(callable $fn)
    {
        if (static::$busy) {
            return $fn();
        }

        static::$busy = true;
        try {
            return $fn();
        }
        finally {
            static::$busy = false;
        }
    }

    public static function isBusy(): bool
    {
        return static::$busy;
    }

    /**
     * Hello → CRM: garantiza que el contacto de chat tenga su contraparte en
     * el CRM del tenant (la crea o la enlaza por teléfono).
     */
    public static function mirror(?HelloContact $hello, string $externalId): ?CrmContact
    {
        if (static::$busy || !$hello || !static::crmEnabled($hello->tenant_id)) {
            return null;
        }

        $digits = PhoneNumber::normalize($externalId);
        if (!$digits) {
            return null;
        }

        return static::quietly(function () use ($hello, $digits) {
            $tenantId = (int) $hello->tenant_id;

            $linked = CrmContact::where('tenant_id', $tenantId)->where('hello_contact_id', $hello->id)->first();
            if ($linked) {
                if (!$linked->phone) {
                    $linked->newQuery()->where('id', $linked->id)->update(['phone' => '+' . $digits]);
                }
                return $linked;
            }

            $existing = static::findByPhone($tenantId, $digits);
            if ($existing) {
                return static::link($existing, $hello) ? $existing : null;
            }

            $name = $hello->hasPlaceholderName() ? null : trim($hello->name);
            [$first, $last] = $name ? static::splitName($name) : ['+' . $digits, null];

            return CrmContact::create([
                'tenant_id'        => $tenantId,
                'first_name'       => $first,
                'last_name'        => $last,
                'phone'            => '+' . $digits,
                'source'           => 'whatsapp',
                'hello_contact_id' => $hello->id,
            ]);
        });
    }

    /**
     * Hello → CRM: el nombre del contacto de chat cambió (edición manual o
     * nombre de WhatsApp sobre un marcador); se copia al CRM si difiere.
     */
    public static function pushName(HelloContact $hello): void
    {
        if (static::$busy || $hello->hasPlaceholderName()) {
            return;
        }

        $crm = CrmContact::where('hello_contact_id', $hello->id)->first();
        $name = trim($hello->name);

        if (!$crm || trim("{$crm->first_name} {$crm->last_name}") === $name) {
            return;
        }

        static::quietly(function () use ($crm, $name) {
            [$first, $last] = static::splitName($name);
            $crm->first_name = $first;
            $crm->last_name = $last;
            $crm->save();
        });
    }

    /**
     * Enlaza un contacto del CRM (ya existente) con un contacto de chat.
     * Si el CRM ya tenía otro espejo en Hello: se descarta cuando está vacío;
     * si ambos tienen historial no se toca nada (devuelve false).
     */
    public static function link(CrmContact $crm, HelloContact $hello): bool
    {
        if ($crm->hello_contact_id && (int) $crm->hello_contact_id !== (int) $hello->id) {
            $old = HelloContact::find($crm->hello_contact_id);

            if ($old && ($old->conversations()->exists() || $old->messages()->exists())) {
                Log::info('aero.crm: contacto no enlazado, ambos lados tienen historial', ['crm' => $crm->id, 'hello' => $hello->id]);
                return false;
            }

            $old?->delete();
        }

        $crm->newQuery()->where('id', $crm->id)->update(['hello_contact_id' => $hello->id]);
        $crm->hello_contact_id = $hello->id;

        $crmName = trim("{$crm->first_name} {$crm->last_name}");

        if (!static::isPlaceholder($crmName) && $hello->hasPlaceholderName()) {
            $hello->update(['name' => $crmName]);
        }
        elseif ($crm && static::isPlaceholder($crmName) && !$hello->hasPlaceholderName()) {
            [$first, $last] = static::splitName(trim($hello->name));
            $crm->first_name = $first;
            $crm->last_name = $last;
            $crm->save();
        }

        return true;
    }

    public static function findByPhone(int $tenantId, string $digits): ?CrmContact
    {
        return CrmContact::where('tenant_id', $tenantId)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->get()
            ->first(fn ($c) => PhoneNumber::normalize($c->phone) === $digits);
    }

    public static function isPlaceholder(?string $name): bool
    {
        return HelloContact::isPlaceholderName($name);
    }

    /**
     * "Ana María Pérez" → ["Ana", "María Pérez"]. Unirlos de nuevo con un
     * espacio devuelve el mismo texto, así el nombre no oscila entre lados.
     */
    public static function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? null];
    }
}
