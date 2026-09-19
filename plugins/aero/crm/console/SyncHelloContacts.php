<?php namespace Aero\Crm\Console;

use Aero\Crm\Classes\HelloSync;
use Aero\Hello\Models\ContactIdentity;
use Illuminate\Console\Command;

/**
 * Pasada única (o de reparación) de la sincronía Hello → CRM: recorre las
 * identidades de WhatsApp de los tenants con CRM activado y crea/enlaza el
 * contacto del CRM que falte. Idempotente.
 */
class SyncHelloContacts extends Command
{
    protected $signature = 'crm:sync-hello
        {--tenant= : Limitar a un tenant}
        {--dry-run : Solo cuenta lo que se crearía o enlazaría, sin escribir}';

    protected $description = 'Crea o enlaza en el CRM los contactos de WhatsApp de Hello que aún no están.';

    public function handle(): int
    {
        if (!HelloSync::available()) {
            $this->error('Aero.Hello no está instalado.');
            return 1;
        }

        $dry = (bool) $this->option('dry-run');
        $stats = ['creados' => 0, 'enlazados' => 0, 'ya' => 0, 'omitidos' => 0];

        ContactIdentity::with('contact')
            ->where('platform', 'whatsapp')
            ->orderBy('id')
            ->chunk(200, function ($identities) use ($dry, &$stats) {
                foreach ($identities as $identity) {
                    $hello = $identity->contact;

                    if (!$hello || !HelloSync::crmEnabled($hello->tenant_id)
                        || ($this->option('tenant') && (int) $this->option('tenant') !== (int) $hello->tenant_id)) {
                        $stats['omitidos']++;
                        continue;
                    }

                    $tenantId = (int) $hello->tenant_id;
                    $digits = \Aero\Hello\Classes\PhoneNumber::normalize($identity->external_id);

                    if (!$digits) {
                        $stats['omitidos']++;
                        continue;
                    }

                    if (\Aero\Crm\Models\Contact::where('tenant_id', $tenantId)->where('hello_contact_id', $hello->id)->exists()) {
                        $stats['ya']++;
                        continue;
                    }

                    if ($dry) {
                        HelloSync::findByPhone($tenantId, $digits) ? $stats['enlazados']++ : $stats['creados']++;
                        continue;
                    }

                    $hadMatch = (bool) HelloSync::findByPhone($tenantId, $digits);
                    $crm = HelloSync::mirror($hello, $identity->external_id);
                    $crm ? ($hadMatch ? $stats['enlazados']++ : $stats['creados']++) : $stats['omitidos']++;
                }
            });

        // Enlace de clientes de tienda para contactos que aún no tienen uno.
        $linked = 0;
        if (class_exists(\Aero\Shop\Models\Customer::class)) {
            \Aero\Crm\Models\Contact::whereNull('shop_customer_id')
                ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', $this->option('tenant')))
                ->chunk(200, function ($contacts) use ($dry, &$linked) {
                    foreach ($contacts as $contact) {
                        if (!$contact->tenant_id
                            || !\Aero\Crm\Classes\ShopCustomerSync::findCustomer((int) $contact->tenant_id, $contact->email, $contact->phone)) {
                            continue;
                        }
                        $linked++;
                        if (!$dry) {
                            $contact->linkShopCustomer();
                        }
                    }
                });
        }
        $stats['clientes_tienda_enlazados'] = $linked;

        $this->info(($dry ? '[simulación] ' : '') . json_encode($stats, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
