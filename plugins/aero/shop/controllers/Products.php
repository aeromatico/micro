<?php namespace Aero\Shop\Controllers;

use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
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
    }

    public function formExtendModel($model): void
    {
        if (!$model->exists) {
            $model->tenant_id = $this->getCurrentTenantId();
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

        $tenantId = (int) ($form->model->tenant_id ?: $this->getCurrentTenantId());
        $restaurant = \Aero\Shop\Models\ShopSettings::isRestaurantForTenant($tenantId);

        if ($restaurant && !$form->model->has_variants) {
            $form->removeField('_options_relation');
            $form->removeField('_variants_relation');
            $form->removeField('has_variants');
        }

        if (!$restaurant) {
            $form->removeField('_modifiers_relation');
            $form->removeField('prep_minutes');
            $form->removeField('min_quantity');
        }
    }
}
