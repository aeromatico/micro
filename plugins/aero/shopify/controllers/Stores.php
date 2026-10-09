<?php namespace Aero\Shopify\Controllers;

use Aero\Shopify\Classes\CurrentTenant;
use Aero\Shopify\Classes\ScopesToTenant;
use Aero\Shopify\Classes\ShopifyClient;
use Aero\Shopify\Models\Store;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Stores extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.shopify.use', 'aero.shopify.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shopify', 'shopify', 'stores');
    }

    /** El tenant solo crea tiendas para sí mismo; el superadmin elige el tenant. */
    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
        }

        if (!$model->tenant_id) {
            throw new \ApplicationException('No se pudo determinar el tenant de la tienda.');
        }
    }

    public function formBeforeSave($model): void
    {
        // Un tenant nunca reasigna la tienda a otro tenant.
        if (!CurrentTenant::isAdmin() && $model->exists) {
            $model->tenant_id = $model->getOriginal('tenant_id');
        }

        $model->setSecrets($model->new_access_token ?? null, $model->new_client_secret ?? null);

        // La cuenta bancaria debe ser del mismo tenant que la tienda.
        if ($model->bank_account_id && !array_key_exists((int) $model->bank_account_id, $model->getBankAccountIdOptions())) {
            throw new \ApplicationException('La cuenta bancaria elegida no pertenece a este tenant.');
        }
    }

    public function update_onTest($recordId = null)
    {
        $store = $this->scopeToTenant(Store::query())->findOrFail($recordId);

        try {
            $name = (new ShopifyClient($store))->shopName();
            Flash::success("Conexión correcta con la tienda «{$name}».");
        } catch (\Throwable $e) {
            Flash::error('No se pudo conectar: ' . $e->getMessage());
        }
    }
}
