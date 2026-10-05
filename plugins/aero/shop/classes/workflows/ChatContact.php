<?php namespace Aero\Shop\Classes\Workflows;

/**
 * Quién es el cliente que está comprando por chat. La clave es su teléfono, así
 * una conversación real y una prueba con `{"telefono": "..."}` comparten carrito.
 * Con un mensaje de Hello se verifica que el contacto sea del mismo tenant.
 */
class ChatContact
{
    /**
     * @return array{key: string, phone: ?string, name: ?string}|null
     */
    public static function resolve(array $data, array $ctx, int $tenantId): ?array
    {
        $explicit = $data['contact'] ?? null;

        if (is_scalar($explicit) && trim((string) $explicit) !== '') {
            return static::fromPhone((string) $explicit, null);
        }

        $trigger = (array) ($ctx['trigger'] ?? []);
        $message = (array) ($trigger['data'][0] ?? []);
        $contactId = $message['contact_id'] ?? $trigger['contact_id'] ?? null;

        if ($contactId && class_exists(\Aero\Hello\Models\Contact::class)) {
            $contact = \Aero\Hello\Models\Contact::forTenant($tenantId)->with('identities')->find($contactId);

            if ($contact) {
                $identity = $contact->identities->firstWhere('platform', 'whatsapp') ?: $contact->identities->first();
                $name = $contact->hasPlaceholderName() ? null : trim((string) $contact->name);

                return $identity
                    ? static::fromPhone((string) $identity->external_id, $name)
                    : ['key' => 'c:' . $contact->id, 'phone' => null, 'name' => $name];
            }
        }

        foreach (['phone', 'telefono', 'from', 'celular', 'contact'] as $field) {
            if (isset($trigger[$field]) && is_scalar($trigger[$field]) && trim((string) $trigger[$field]) !== '') {
                return static::fromPhone((string) $trigger[$field], isset($trigger['name']) ? trim((string) $trigger['name']) : null);
            }
        }

        return null;
    }

    protected static function fromPhone(string $raw, ?string $name): ?array
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (strlen($digits) >= 6) {
            return ['key' => 'p:' . $digits, 'phone' => $digits, 'name' => $name ?: null];
        }

        $text = mb_strtolower(trim($raw));

        return $text === '' ? null : ['key' => 'x:' . mb_substr($text, 0, 60), 'phone' => null, 'name' => $name ?: null];
    }
}
