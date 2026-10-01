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

        $this->settingsWidget = $this->makeSettingsWidget($settings, $tenant->id);
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
            'store_mode'                  => in_array($data['store_mode'] ?? '', ['standard', 'whatsapp'], true) ? $data['store_mode'] : 'standard',
            'whatsapp_mode'               => in_array($data['whatsapp_mode'] ?? '', ['api', 'market'], true) ? $data['whatsapp_mode'] : 'api',
            'whatsapp_number'             => preg_replace('/\D+/', '', (string) ($data['whatsapp_number'] ?? '')) ?: null,
            'whatsapp_account_id'         => $this->ownedAccountId($tenant->id, $data['whatsapp_account_id'] ?? null),
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

    /** Solo cuentas de Hello del propio tenant: nunca se confía en el id que llega del formulario. */
    protected function ownedAccountId(int $tenantId, $id): ?int
    {
        if (!$id || !class_exists(\Aero\Hello\Models\Account::class)) {
            return null;
        }

        return \Aero\Hello\Models\Account::forTenant($tenantId)->whereKey($id)->value('id');
    }

    protected function whatsappAccountOptions(int $tenantId): array
    {
        if (!class_exists(\Aero\Hello\Models\Account::class)) {
            return [];
        }

        return \Aero\Hello\Models\Account::forTenant($tenantId)->ofPlatform('whatsapp')->orderBy('label')->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->label . ($a->phone_number ? " (+{$a->phone_number})" : '')])
            ->all();
    }

    protected function getTenantCurrencies(int $tenantId)
    {
        return TenantCurrency::forTenant($tenantId)->with('currency')->get();
    }

    protected function makeSettingsWidget(ShopSettingsModel $model, int $tenantId): Form
    {
        $config            = new \stdClass;
        $config->model     = $model;
        $config->arrayName = 'ShopSettings';
        $config->alias     = 'shopSettingsForm';
        $config->fields    = [
            'store_mode' => [
                'label'   => 'Tipo de tienda',
                'type'    => 'balloon-selector',
                'default' => 'standard',
                'options' => [
                    'standard' => 'Tienda estándar',
                    'whatsapp' => 'Tienda para WhatsApp',
                ],
                'comment' => 'La tienda para WhatsApp simplifica el checkout: solo pide el celular del cliente y envía el pedido al chat.',
            ],
            'whatsapp_mode' => [
                'label'   => 'Envío del pedido',
                'type'    => 'balloon-selector',
                'default' => 'api',
                'options' => [
                    'api'    => 'WhatsApp Api',
                    'market' => 'Market Api',
                ],
                'comment' => 'WhatsApp Api: el cliente abre WhatsApp con el pedido ya escrito hacia tu número. Market Api: el pedido se envía al cliente desde una de tus cuentas de Hello.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[whatsapp]'],
            ],
            'whatsapp_number' => [
                'label'       => 'Número de WhatsApp de la tienda',
                'type'        => 'text',
                'span'        => 'left',
                'placeholder' => '59171234567',
                'comment'     => 'Con código de país, solo dígitos. Es el número que recibe los pedidos.',
                'trigger'     => ['action' => 'show', 'field' => 'whatsapp_mode', 'condition' => 'value[api]'],
            ],
            'whatsapp_account_id' => [
                'label'       => 'Cuenta de Hello',
                'type'        => 'dropdown',
                'span'        => 'left',
                'options'     => $this->whatsappAccountOptions($tenantId),
                'placeholder' => 'Selecciona la cuenta que enviará los pedidos',
                'comment'     => 'Se administran en Hello → Cuentas.',
                'trigger'     => ['action' => 'show', 'field' => 'whatsapp_mode', 'condition' => 'value[market]'],
            ],
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
