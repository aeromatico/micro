<?php namespace Aero\Crm\Classes;

use Aero\Crm\Models\Contact;
use Aero\Crm\Models\CrmSettings;
use Aero\Shop\Models\Customer;

/**
 * ShopCustomerSync mantiene un Aero\Crm\Models\Contact en espejo de cada
 * Aero\Shop\Models\Customer, para el tenant donde el CRM está activado.
 * Se dispara desde Aero\Crm\Plugin::bootShopCustomerSync() en el evento
 * `model.afterCreate` de Customer.
 */
class ShopCustomerSync
{
    /**
     * Cliente de la tienda del tenant que corresponde a un email o teléfono
     * (email primero: es único; el teléfono se compara normalizado).
     */
    public static function findCustomer(int $tenantId, ?string $email, ?string $phone): ?Customer
    {
        if ($email) {
            $byEmail = Customer::where('tenant_id', $tenantId)->where('email', $email)->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        $digits = \Aero\Hello\Classes\PhoneNumber::normalize($phone);
        if (!$digits) {
            return null;
        }

        // Prefiltro en SQL por los últimos 8 dígitos (el teléfono se guardó
        // con formatos distintos) y comparación exacta ya normalizada.
        $tail = substr($digits, -8);
        $stripped = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+',''),'(',''),')','')";

        return Customer::where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->whereRaw("{$stripped} LIKE ?", ['%' . $tail])
            ->get()
            ->first(fn ($c) => \Aero\Hello\Classes\PhoneNumber::normalize($c->phone) === $digits);
    }

    /**
     * syncContactFromCustomer crea (o vincula, si ya existe uno con el
     * mismo email) el Contact del CRM correspondiente a un Customer de
     * tienda recién creado.
     */
    public static function syncContactFromCustomer(Customer $customer): void
    {
        if (!$customer->tenant_id || !$customer->email) {
            return;
        }

        $crmEnabled = CrmSettings::where('tenant_id', $customer->tenant_id)->value('is_enabled');
        if (!$crmEnabled) {
            return;
        }

        $contact = Contact::where('tenant_id', $customer->tenant_id)
            ->where('email', $customer->email)
            ->first();

        if ($contact) {
            if (!$contact->shop_customer_id) {
                $contact->shop_customer_id = $customer->id;
                $contact->save();
            }
            return;
        }

        Contact::create([
            'tenant_id'        => $customer->tenant_id,
            'first_name'       => $customer->first_name,
            'last_name'        => $customer->last_name,
            'email'            => $customer->email,
            'phone'            => $customer->phone,
            'source'           => 'shop',
            'shop_customer_id' => $customer->id,
        ]);
    }
}
