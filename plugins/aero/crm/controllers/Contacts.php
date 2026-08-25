<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Models\Activity;
use Aero\Crm\Models\Contact;
use Aero\Crm\Models\ContactList;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

class Contacts extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Aero\Sites\Behaviors\VersatileImportExportController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $importExportConfig = 'config_import_export.yaml';

    public $requiredPermissions = ['aero.crm.manage_contacts'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-contactos');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
        }
    }

    /**
     * Opciones para el <select> de "Agregar a lista" en _list_toolbar.htm.
     */
    public function getContactListOptions(): array
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return [];
        }

        return ContactList::forTenant($tenantId)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Agrega los contactos seleccionados en el listado a la lista elegida
     * en el toolbar. Idempotente: syncWithoutDetaching no duplica ni quita
     * membresías existentes en otras listas.
     */
    public function onAddToList()
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            Flash::error('No se pudo determinar el tenant actual.');
            return;
        }

        $listId = post('contact_list_id');
        if (!$listId) {
            Flash::error('Elige una lista antes de continuar.');
            return;
        }

        $list = ContactList::forTenant($tenantId)->find($listId);
        if (!$list) {
            Flash::error('Lista no encontrada.');
            return;
        }

        $ids = Contact::forTenant($tenantId)
            ->whereIn('id', post('checked', []))
            ->pluck('id');

        $list->contacts()->syncWithoutDetaching($ids);

        Flash::success($ids->count() . ' contacto(s) agregado(s) a "' . $list->name . '".');
        return $this->listRefresh();
    }

    /**
     * Crea (si hace falta) un Aero\Hello\Models\Contact y lo enlaza a este
     * Contact del CRM, para poder enviarle WhatsApp/email desde aquí.
     */
    public function onLinkHelloContact($recordId = null)
    {
        if (!class_exists(\Aero\Hello\Models\Contact::class)) {
            Flash::error('El plugin Aero.Hello no está instalado.');
            return;
        }

        $contact = Contact::forTenant($this->getCurrentTenantId())->findOrFail($recordId ?: post('record_id'));

        if (!$contact->hello_contact_id) {
            $helloContact = \Aero\Hello\Models\Contact::create([
                'tenant_id' => $this->getCurrentTenantId(),
                'name'      => $contact->full_name,
            ]);
            $contact->hello_contact_id = $helloContact->id;
            $contact->save();
        }

        Flash::success('Contacto vinculado con Hello. Agrega su número/identidad de WhatsApp desde Hello → Contactos.');
        return $this->formRefresh();
    }

    /**
     * Envía un mensaje (WhatsApp o email) vía Aero\Hello\Jobs\SendMessageJob
     * usando el hello_contact_id vinculado, y registra la actividad en el CRM.
     */
    public function onSendMessage($recordId = null)
    {
        if (!class_exists(\Aero\Hello\Models\Contact::class)) {
            Flash::error('El plugin Aero.Hello no está instalado.');
            return;
        }

        $contact = Contact::forTenant($this->getCurrentTenantId())->findOrFail($recordId ?: post('record_id'));
        $body    = trim((string) post('message_body'));
        $type    = post('message_type', 'whatsapp');

        if (!$contact->hello_contact_id) {
            Flash::error('Este contacto todavía no está vinculado a Hello.');
            return $this->formRefresh();
        }

        if ($body === '') {
            Flash::error('Escribe un mensaje antes de enviar.');
            return;
        }

        $helloContact = \Aero\Hello\Models\Contact::with('identities')->find($contact->hello_contact_id);

        try {
            \Aero\Hello\Classes\Hello::sendToContact($helloContact, $body, [
                'platform'  => $type === 'email' ? 'email' : 'whatsapp',
                'tenant_id' => $this->getCurrentTenantId(),
            ]);
        }
        catch (\Throwable $ex) {
            Flash::error($ex->getMessage());
            return $this->formRefresh();
        }

        Activity::create([
            'tenant_id'   => $this->getCurrentTenantId(),
            'related_type' => Contact::class,
            'related_id'    => $contact->id,
            'type'          => $type === 'email' ? 'email' : 'whatsapp',
            'subject'       => 'Mensaje enviado',
            'description'   => $body,
            'completed_at'  => now(),
            'owner_id'      => BackendAuth::getUser()?->id,
        ]);

        Flash::success('Mensaje encolado para envío.');
        return $this->formRefresh();
    }

    /**
     * Convierte este contacto del CRM en un Aero\Shop\Models\Customer, para
     * que pueda hacer compras en la tienda del tenant. Reutiliza uno
     * existente con el mismo email si ya hay uno (evita duplicados).
     */
    public function onConvertToShopCustomer($recordId = null)
    {
        if (!class_exists(\Aero\Shop\Models\Customer::class)) {
            Flash::error('El plugin Aero.Shop no está instalado.');
            return;
        }

        $contact = Contact::forTenant($this->getCurrentTenantId())->findOrFail($recordId ?: post('record_id'));

        if ($contact->shop_customer_id) {
            Flash::error('Este contacto ya está vinculado a un cliente de tienda.');
            return $this->formRefresh();
        }

        if (!$contact->email) {
            Flash::error('El contacto necesita un email para convertirse en cliente de tienda.');
            return $this->formRefresh();
        }

        $customer = \Aero\Shop\Models\Customer::forTenant($this->getCurrentTenantId())
            ->where('email', $contact->email)
            ->first();

        if (!$customer) {
            $customer = \Aero\Shop\Models\Customer::create([
                'tenant_id'  => $this->getCurrentTenantId(),
                'email'      => $contact->email,
                'first_name' => $contact->first_name,
                'last_name'  => $contact->last_name,
                'phone'      => $contact->phone,
            ]);
        }

        $contact->shop_customer_id = $customer->id;
        $contact->save();

        Flash::success('Contacto convertido en cliente de tienda.');
        return $this->formRefresh();
    }
}
