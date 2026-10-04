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
            'branches_enabled'            => (bool) ($data['branches_enabled'] ?? false),
            'branches'                    => $this->branchesFrom($data),
            'base_currency_id'            => $data['base_currency_id'] ?: null,
            'inventory_tracking_enabled'  => (bool) ($data['inventory_tracking_enabled'] ?? false),
            'guest_checkout_enabled'      => (bool) ($data['guest_checkout_enabled'] ?? false),
            'order_number_prefix'         => $data['order_number_prefix'] ?: null,
            'store_mode'                  => in_array($data['store_mode'] ?? '', ['standard', 'whatsapp', 'restaurant'], true) ? $data['store_mode'] : 'standard',
            'whatsapp_mode'               => in_array($data['whatsapp_mode'] ?? '', ['api', 'market'], true) ? $data['whatsapp_mode'] : 'api',
            'whatsapp_number'             => preg_replace('/\D+/', '', (string) ($data['whatsapp_number'] ?? '')) ?: null,
            'whatsapp_account_id'         => $this->ownedAccountId($tenant->id, $data['whatsapp_account_id'] ?? null),
            'restaurant_config'           => $this->restaurantConfigFrom($data, !empty($settings->restaurant()['busy'])),
            'schedule_mode'               => in_array($data['schedule_mode'] ?? '', ['always_open', 'scheduled', 'online'], true) ? $data['schedule_mode'] : 'always_open',
            'accepting_orders'            => (bool) ($data['accepting_orders'] ?? true),
            'timezone'                    => in_array($data['timezone'] ?? '', \DateTimeZone::listIdentifiers(), true) ? $data['timezone'] : 'America/La_Paz',
            'schedule_hours'              => $this->scheduleHoursFrom($data),
            'low_stock_threshold'         => is_numeric($data['low_stock_threshold'] ?? '') ? (int) $data['low_stock_threshold'] : null,
        ]);
        $settings->save();

        Flash::success('Configuración de la tienda guardada.');
        return [];
    }

    /** QR imprimibles de cada mesa: /tienda?mesa=N en el dominio del tenant. */
    public function onTableQrs()
    {
        $tenant   = $this->getCurrentTenant();
        $settings = ShopSettingsModel::where('tenant_id', $tenant->id)->first();
        $tables   = (int) ($settings?->restaurant()['tables'] ?? 0);
        $qrs      = [];

        if ($settings?->isRestaurantStore() && $tenant->primary_domain) {
            $writer = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220, 1),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            ));
            for ($i = 1; $i <= $tables; $i++) {
                $url = 'https://' . $tenant->primary_domain . '/tienda?mesa=' . $i;
                $qrs[$i] = ['url' => $url, 'svg' => $writer->writeString($url)];
            }
        }

        return ['#table-qrs' => $this->makePartial('table_qrs', ['qrs' => $qrs])];
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

    protected function scheduleHoursFrom(array $data): array
    {
        $hours = [];
        foreach ((array) ($data['schedule_hours'] ?? []) as $h) {
            if (!array_key_exists($h['day'] ?? '', ShopSettingsModel::DAYS)) {
                continue;
            }
            $hours[] = [
                'day'    => $h['day'],
                'open'   => preg_match('/^\d{2}:\d{2}/', (string) ($h['open'] ?? '')) ? substr($h['open'], 0, 5) : null,
                'close'  => preg_match('/^\d{2}:\d{2}/', (string) ($h['close'] ?? '')) ? substr($h['close'], 0, 5) : null,
                'closed' => (bool) ($h['closed'] ?? false),
            ];
        }

        return $hours;
    }

    protected function restaurantConfigFrom(array $data, bool $busy = false): array
    {
        $types = array_values(array_intersect((array) ($data['rc_order_types'] ?? []), array_keys(ShopSettingsModel::ORDER_TYPES)));

        return [
            'order_types'   => $types ?: ['pickup'],
            'delivery_fee'  => max(0, (float) ($data['rc_delivery_fee'] ?? 0)),
            'default_prep'  => min(240, max(1, (int) ($data['rc_default_prep'] ?? 15))),
            'capacity'      => min(100, max(1, (int) ($data['rc_capacity'] ?? 3))),
            'load_minutes'  => min(60, max(0, (int) ($data['rc_load_minutes'] ?? 3))),
            'busy_extra'    => min(120, max(0, (int) ($data['rc_busy_extra'] ?? 10))),
            'delivery_extra' => min(240, max(0, (int) ($data['rc_delivery_extra'] ?? 15))),
            'busy'          => $busy,
            'lead_minutes'  => min(1440, max(0, (int) ($data['rc_lead_minutes'] ?? 30))),
            'tables'        => min(500, max(0, (int) ($data['rc_tables'] ?? 0))),
            'accept_closed' => (bool) ($data['rc_accept_closed'] ?? false),
        ];
    }

    /** Solo cuentas de Hello del propio tenant: nunca se confía en el id que llega del formulario. */
    /** Normaliza las filas del repeater; las sucursales nuevas reciben un id estable. */
    protected function branchesFrom(array $data): array
    {
        $rows = [];
        $ids = [];

        foreach ((array) ($data['branches'] ?? []) as $row) {
            $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 120);
            if ($name === '') {
                continue;
            }

            $id = preg_match('/^[a-z0-9]{6,12}$/', (string) ($row['id'] ?? '')) && !in_array($row['id'], $ids, true)
                ? $row['id']
                : strtolower(\Illuminate\Support\Str::random(8));
            $ids[] = $id;

            $rows[] = [
                'id'        => $id,
                'name'      => $name,
                'address'   => mb_substr(trim((string) ($row['address'] ?? '')), 0, 255) ?: null,
                'phone'     => mb_substr(trim((string) ($row['phone'] ?? '')), 0, 30) ?: null,
                'is_active' => !empty($row['is_active']),
            ];
        }

        return $rows;
    }

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
                    'restaurant' => 'Restaurante',
                ],
                'comment' => 'La tienda para WhatsApp simplifica el checkout: solo pide el celular del cliente y envía el pedido al chat. Restaurante agrega extras por plato, tipo de pedido (local, recoger, delivery), mesas por QR y horarios.',
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
            'rc_section' => [
                'label'   => 'Restaurante',
                'type'    => 'section',
                'comment' => 'Carta con extras y notas por plato, tipo de pedido, mesas por QR y horarios.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_order_types' => [
                'label'   => 'Tipos de pedido que aceptas',
                'type'    => 'checkboxlist',
                'default' => ['dine_in', 'pickup', 'delivery'],
                'options' => ShopSettingsModel::ORDER_TYPES,
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_delivery_fee' => [
                'label' => 'Costo de delivery', 'type' => 'number', 'span' => 'left', 'default' => 0,
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_tables' => [
                'label' => 'Cantidad de mesas', 'type' => 'number', 'span' => 'right', 'default' => 0,
                'comment' => 'Cada mesa tiene un QR (/tienda?mesa=N) que prellena la mesa del pedido. Ver «QR de mesas» abajo.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_default_prep' => [
                'label' => 'Preparación por defecto (min)', 'type' => 'number', 'span' => 'left', 'default' => 15,
                'comment' => 'Se usa si el plato no tiene su propio tiempo de preparación.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_capacity' => [
                'label' => 'Capacidad de cocina (pedidos a la vez)', 'type' => 'number', 'span' => 'right', 'default' => 3,
                'comment' => 'Hasta este número de pedidos en cocina no se suma demora.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_load_minutes' => [
                'label' => 'Minutos extra por pedido en espera', 'type' => 'number', 'span' => 'left', 'default' => 3,
                'comment' => 'Por cada pedido por encima de la capacidad.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_busy_extra' => [
                'label' => 'Minutos extra en «modo ocupado»', 'type' => 'number', 'span' => 'right', 'default' => 10,
                'comment' => 'Cocina enciende el modo ocupado desde la pantalla de Cocina.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_delivery_extra' => [
                'label' => 'Tiempo de reparto (min)', 'type' => 'number', 'span' => 'left', 'default' => 15,
                'comment' => 'Asignar repartidor y trayecto; se suma solo a pedidos de delivery.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_lead_minutes' => [
                'label' => 'Anticipación mínima de pedidos programados (min)', 'type' => 'number', 'span' => 'left', 'default' => 30,
                'comment' => 'Un pedido programado debe pedirse con al menos este tiempo de anticipación.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_accept_closed' => [
                'label' => 'Aceptar pedidos fuera de horario', 'type' => 'switch',
                'comment' => 'Si está activo, el cliente puede programar su pedido; si no, la tienda se bloquea al cerrar.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'schedule_mode' => [
                'label'   => 'Horario de la tienda',
                'type'    => 'balloon-selector',
                'default' => 'always_open',
                'options' => ['always_open' => 'Abierto 24 horas', 'scheduled' => 'Por horario', 'online' => 'Atención en línea'],
                'comment' => 'Fuera de horario la tienda no acepta pedidos. «Atención en línea»: tú abres y cierras con un interruptor.',
            ],
            'accepting_orders' => [
                'label'   => 'Recibiendo pedidos ahora',
                'type'    => 'switch',
                'default' => true,
                'trigger' => ['action' => 'show', 'field' => 'schedule_mode', 'condition' => 'value[online]'],
            ],
            'timezone' => [
                'label'   => 'Zona horaria',
                'type'    => 'dropdown',
                'default' => 'America/La_Paz',
                'options' => array_combine(\DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA), \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA)),
                'trigger' => ['action' => 'show', 'field' => 'schedule_mode', 'condition' => 'value[scheduled]'],
            ],
            'schedule_hours' => [
                'label'     => 'Horarios',
                'type'      => 'repeater',
                'prompt'    => 'Agregar horario',
                'titleFrom' => 'day',
                'comment'   => 'Una fila por día; repite el día para turnos partidos. Un cierre menor a la apertura cruza la medianoche.',
                'form'      => ['fields' => [
                    'day'    => ['label' => 'Día', 'type' => 'dropdown', 'span' => 'left', 'options' => ShopSettingsModel::DAYS],
                    'closed' => ['label' => 'Cerrado todo el día', 'type' => 'switch', 'span' => 'right'],
                    'open'   => ['label' => 'Abre', 'type' => 'text', 'span' => 'left', 'placeholder' => '08:00'],
                    'close'  => ['label' => 'Cierra', 'type' => 'text', 'span' => 'right', 'placeholder' => '22:00'],
                ]],
                'trigger'   => ['action' => 'show', 'field' => 'schedule_mode', 'condition' => 'value[scheduled]'],
            ],
            'branches_enabled' => [
                'label'   => 'Manejar sucursales',
                'type'    => 'switch',
                'span'    => 'left',
                'default' => false,
                'comment' => 'Apagado, la tienda es un solo negocio. Encendido, el checkout pide elegir una sucursal.',
            ],
            'branches' => [
                'label'   => 'Sucursales',
                'type'    => 'repeater',
                'span'    => 'full',
                'prompt'  => 'Agregar sucursal',
                'trigger' => ['action' => 'show', 'field' => 'branches_enabled', 'condition' => 'checked'],
                'form'    => [
                    'fields' => [
                        'id'        => ['type' => 'hidden'],
                        'name'      => ['label' => 'Nombre', 'type' => 'text', 'span' => 'left'],
                        'phone'     => ['label' => 'Teléfono', 'type' => 'text', 'span' => 'right'],
                        'address'   => ['label' => 'Dirección', 'type' => 'text', 'span' => 'full'],
                        'is_active' => ['label' => 'Activa', 'type' => 'switch', 'span' => 'left', 'default' => true],
                    ],
                ],
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
