<?php namespace Aero\Shop\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use Aero\Shop\Models\Seller;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;

class Products extends Controller
{
    use ResolvesCurrentTenant;

    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = ['aero.shop.manage_products'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shop', 'tienda', 'shop-productos');
    }

    public function listExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
        $query->where('is_internal', false);
        Seller::restrictQuery($query, BackendAuth::getUser(), $this->getCurrentTenantId());
    }

    public function formExtendQuery($query): void
    {
        $this->scopeQueryToTenant($query);
        Seller::restrictQuery($query, BackendAuth::getUser(), $this->getCurrentTenantId());
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
            $model->type = 'physical';
        }
    }

    /** Marketplace: el comerciante vende solo lo suyo; el admin elige el dueño (o lo deja del tenant). */
    public function formBeforeSave($model): void
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId || !\Aero\Shop\Models\ShopSettings::isMarketplaceForTenant($tenantId)) {
            return;
        }

        $user = BackendAuth::getUser();
        if (!Seller::seesEverything($user, $tenantId)) {
            $mine = Seller::forUser($user, $tenantId);
            if (!$mine) {
                throw new \ApplicationException('No estás registrado como comerciante de esta tienda.');
            }
            $model->seller_id = $mine->id;
        } elseif ($model->seller_id && !Seller::forTenant($tenantId)->where('id', $model->seller_id)->exists()) {
            throw new \ApplicationException('El comerciante elegido no pertenece a tu tienda.');
        }
    }

    /** Solo el admin ve y elige el comerciante dueño del producto. */
    protected function sellerFieldOptions(): ?array
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId || !\Aero\Shop\Models\ShopSettings::isMarketplaceForTenant($tenantId)
            || !Seller::seesEverything(BackendAuth::getUser(), $tenantId)) {
            return null;
        }

        return ['' => '— Producto de la tienda (sin comerciante) —']
            + Seller::forTenant($tenantId)->active()->orderBy('display_name')->pluck('display_name', 'id')->all();
    }

    protected function isRestaurantTenant(): bool
    {
        $tenantId = $this->getCurrentTenantId();

        return $tenantId && \Aero\Shop\Models\ShopSettings::isRestaurantForTenant($tenantId);
    }

    /** En el listado de un restaurante no tiene sentido la columna ni el filtro «Tipo». */
    public function listExtendColumns($list): void
    {
        if ($this->isRestaurantTenant()) {
            $list->removeColumn('type');
        }
    }

    public function listFilterExtendScopes($filter): void
    {
        if ($this->isRestaurantTenant()) {
            $filter->removeScope('type');
        }
    }

    /**
     * Pestañas según el tipo de tienda: Restaurante usa su pestaña (extras,
     * tiempo, mínimo) en vez de Variantes; las demás no la ven. Un plato que ya tiene variantes
     * conserva sus pestañas para no dejar esos datos huérfanos.
     */
    public function formExtendFields($form): void
    {
        if (!$form->model instanceof \Aero\Shop\Models\Product) {
            return;
        }

        if (($sellerOptions = $this->sellerFieldOptions()) !== null) {
            $form->addFields(['seller_id' => [
                'label' => 'Comerciante', 'type' => 'dropdown', 'options' => $sellerOptions,
                'span' => 'auto', 'comment' => 'Quién vende este producto y cobra en su cuenta.',
            ]]);
        }

        $tenantId = (int) ($form->model->tenant_id ?: $this->getCurrentTenantId());
        $restaurant = \Aero\Shop\Models\ShopSettings::isRestaurantForTenant($tenantId);

        // Un restaurante solo vende platos (físicos): el tipo de producto no se pregunta.
        if ($restaurant) {
            $form->removeField('type');
            $form->removeField('digital_file');
        }

        if ($restaurant && !$form->model->has_variants) {
            $form->removeField('_options_relation');
            $form->removeField('_variants_relation');
            $form->removeField('has_variants');
        }

        // Sin sistema de inventario no se pide stock: el campo vacío ya no puede romper el guardado.
        if (!\Aero\Shop\Models\ShopSettings::inventoryEnabledForTenant($tenantId)) {
            foreach (['track_inventory', 'allow_backorder', 'stock_quantity'] as $field) {
                $form->removeField($field);
            }
        }

        if (!$restaurant) {
            $form->removeField('_modifiers_relation');
            $form->removeField('prep_minutes');
            $form->removeField('min_quantity');
        }
    }
}
