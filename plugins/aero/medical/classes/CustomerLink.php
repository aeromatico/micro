<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Patient;

/**
 * Puente blando con Aero.Office: los datos de contacto viven en el Customer
 * (persona en humano, tutor en animal) y no se duplican aquí. Sin Office el
 * paciente funciona sin vínculo.
 */
class CustomerLink
{
    public static function available(): bool
    {
        return class_exists(\Aero\Office\Models\Customer::class);
    }

    /**
     * Si el paciente no tiene Customer pero se dieron datos de contacto,
     * reutiliza el que coincida (teléfono/correo) o crea uno en el mismo tenant.
     */
    public static function ensure(Patient $p): void
    {
        if (!static::available() || $p->customer_id || !$p->tenant_id) {
            return;
        }

        $phone = (string) ($p->contact_phone ?? '');
        $email = (string) ($p->contact_email ?? '');
        $name = $p->kind === Mode::ANIMAL ? (string) ($p->contact_name ?? '') : $p->name;

        if ($name === '' || ($phone === '' && $email === '' && !($p->kind === Mode::HUMAN && $p->document))) {
            return;
        }

        $customer = \Aero\Office\Models\Customer::findMatch((int) $p->tenant_id, $email ?: null, $phone ?: null)
            ?? \Aero\Office\Models\Customer::create([
                'tenant_id' => $p->tenant_id,
                'name'      => $name,
                'phone'     => $phone ?: null,
                'email'     => $email ?: null,
                'document'  => $p->kind === Mode::HUMAN ? $p->document : null,
            ]);

        $p->customer_id = $customer->id;
    }

    /** Clientes de Office del tenant del paciente (nunca de otro). */
    public static function options(?int $tenantId): array
    {
        if (!static::available() || !$tenantId) {
            return [];
        }

        return \Aero\Office\Models\Customer::forTenant($tenantId)->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->mapWithKeys(fn ($c) => [$c->id => trim($c->name . ($c->phone ? ' · ' . $c->phone : ''))])->all();
    }
}
