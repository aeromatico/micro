<?php namespace Aero\Shop\Controllers;

use Aero\Shop\Models\Currency;
use Aero\Shop\Models\ShopSettings as ShopSettingsModel;
use Aero\Shop\Models\TenantCurrency;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;

class ShopSettings extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.shop.manage_settings'];

    public ?Form $settingsWidget = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Shop', 'tienda', 'shop-configuracion');
    }

    public function index()
    {
        $this->pageTitle = 'Configuración de la tienda';
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            $this->vars['noTenant'] = true;
            return;
        }

        $settings = ShopSettingsModel::firstOrCreate(['tenant_id' => $tenant->id], ['is_enabled' => false]);

        $this->settingsWidget = $this->makeSettingsWidget($settings);
        $this->vars['tenant']    = $tenant;
        $this->vars['settings']  = $settings;
        $this->vars['currencies'] = $this->getTenantCurrencies($tenant->id);
        $this->vars['allCurrencies'] = Currency::where('is_active', true)->orderBy('code')->get();
    }

    public function onSave()
    {
        $tenant   = $this->getCurrentTenant();
        $settings = ShopSettingsModel::firstOrCreate(['tenant_id' => $tenant->id]);
        $data     = post('ShopSettings', []);

        $settings->fill([
            'is_enabled'                  => (bool) ($data['is_enabled'] ?? false),
            'base_currency_id'            => $data['base_currency_id'] ?: null,
            'inventory_tracking_enabled'  => (bool) ($data['inventory_tracking_enabled'] ?? false),
            'guest_checkout_enabled'      => (bool) ($data['guest_checkout_enabled'] ?? false),
            'order_number_prefix'         => $data['order_number_prefix'] ?: null,
            'low_stock_threshold'         => is_numeric($data['low_stock_threshold'] ?? '') ? (int) $data['low_stock_threshold'] : null,
        ]);
        $settings->save();

        Flash::success('Configuración de la tienda guardada.');
        return [];
    }

    public function onAddCurrency()
    {
        $tenant     = $this->getCurrentTenant();
        $currencyId = (int) post('currency_id');
        $rate       = (float) post('exchange_rate', 1);

        TenantCurrency::updateOrCreate(
            ['tenant_id' => $tenant->id, 'currency_id' => $currencyId],
            ['exchange_rate' => $rate, 'updated_manually_at' => now()]
        );

        Flash::success('Moneda agregada/actualizada.');

        return [
            '#currencies-list' => $this->makePartial('currencies_list', [
                'currencies' => $this->getTenantCurrencies($tenant->id),
            ]),
        ];
    }

    public function onRemoveCurrency()
    {
        $tenant = $this->getCurrentTenant();
        $id     = (int) post('id');

        TenantCurrency::forTenant($tenant->id)->findOrFail($id)->delete();

        Flash::success('Moneda eliminada.');

        return [
            '#currencies-list' => $this->makePartial('currencies_list', [
                'currencies' => $this->getTenantCurrencies($tenant->id),
            ]),
        ];
    }

    protected function getTenantCurrencies(int $tenantId)
    {
        return TenantCurrency::forTenant($tenantId)->with('currency')->get();
    }

    protected function makeSettingsWidget(ShopSettingsModel $model): Form
    {
        $config            = new \stdClass;
        $config->model     = $model;
        $config->arrayName = 'ShopSettings';
        $config->alias     = 'shopSettingsForm';
        $config->fields    = [
            'is_enabled' => [
                'label'   => 'Tienda activada',
                'type'    => 'switch',
                'span'    => 'left',
                'default' => false,
            ],
            'base_currency_id' => [
                'label'       => 'Moneda base',
                'type'        => 'dropdown',
                'span'        => 'right',
                'options'     => Currency::where('is_active', true)->pluck('name', 'id')->all(),
                'placeholder' => 'Selecciona la moneda base',
            ],
            'inventory_tracking_enabled' => [
                'label'   => 'Usar sistema de inventario',
                'type'    => 'switch',
                'span'    => 'left',
                'default' => true,
                'comment' => 'Si se desactiva, la tienda no valida ni descuenta stock al vender — útil para catálogos bajo pedido, servicios o clientes que no gestionan inventario. El menú "Inventario" se oculta mientras esté apagado.',
            ],
            'guest_checkout_enabled' => [
                'label' => 'Permitir compra como invitado',
                'type'  => 'switch',
                'span'  => 'right',
            ],
            'order_number_prefix' => [
                'label'       => 'Prefijo de número de pedido',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => 'ORD-',
            ],
            'low_stock_threshold' => [
                'label'   => 'Umbral de stock bajo',
                'type'    => 'number',
                'span'    => 'right',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }
}
