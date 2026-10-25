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

        if (($data['store_mode'] ?? '') === 'marketplace' && !ShopSettingsModel::marketplacePurchasedBy((int) $tenant->id)) {
            throw new \ApplicationException('Marketplace requiere contratar el servicio «Marketplace» (4 monedas de oro al mes) en el App Store.');
        }

        $settings->fill([
            'is_enabled'                  => (bool) ($data['is_enabled'] ?? false),
            'branches_enabled'            => (bool) ($data['branches_enabled'] ?? false),
            'branches'                    => $this->branchesFrom($data),
            'base_currency_id'            => $data['base_currency_id'] ?: null,
            'inventory_tracking_enabled'  => (bool) ($data['inventory_tracking_enabled'] ?? false),
            'guest_checkout_enabled'      => (bool) ($data['guest_checkout_enabled'] ?? false),
            'order_number_prefix'         => $data['order_number_prefix'] ?: null,
            'store_mode'                  => in_array($data['store_mode'] ?? '', ['standard', 'whatsapp', 'restaurant', 'marketplace'], true) ? $data['store_mode'] : 'standard',
            'whatsapp_mode'               => in_array($data['whatsapp_mode'] ?? '', ['api', 'market'], true) ? $data['whatsapp_mode'] : 'api',
            'whatsapp_number'             => preg_replace('/\D+/', '', (string) ($data['whatsapp_number'] ?? '')) ?: null,
            'whatsapp_account_id'         => $this->ownedAccountId($tenant->id, $data['whatsapp_account_id'] ?? null),
            'restaurant_config'           => $this->restaurantConfigFrom($data, !empty($settings->restaurant()['busy']), (int) $tenant->id),
            'schedule_mode'               => in_array($data['schedule_mode'] ?? '', ['always_open', 'scheduled', 'online'], true) ? $data['schedule_mode'] : 'always_open',
            'accepting_orders'            => (bool) ($data['accepting_orders'] ?? true),
            'feedback_mode'               => array_key_exists($data['feedback_mode'] ?? '', ShopSettingsModel::FEEDBACK_MODES) ? $data['feedback_mode'] : 'off',
            'timezone'                    => in_array($data['timezone'] ?? '', \DateTimeZone::listIdentifiers(), true) ? $data['timezone'] : 'America/La_Paz',
            'schedule_hours'              => $this->scheduleHoursFrom($data),
            'low_stock_threshold'         => is_numeric($data['low_stock_threshold'] ?? '') ? (int) $data['low_stock_threshold'] : null,
            'shipping_fee'                => max(0, (float) ($data['shipping_fee'] ?? 0)),
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

    protected function restaurantConfigFrom(array $data, bool $busy = false, int $tenantId = 0): array
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
            'qr_dine_in'    => (bool) ($data['rc_qr_dine_in'] ?? false),
            'hello_account_id' => $tenantId ? $this->ownedAccountId($tenantId, $data['rc_hello_account_id'] ?? null) : null,
            'panic_button'  => (bool) ($data['rc_panic_button'] ?? false),
            'wifi_name'     => mb_substr(trim((string) ($data['rc_wifi_name'] ?? '')), 0, 60),
            'wifi_password' => mb_substr(trim((string) ($data['rc_wifi_password'] ?? '')), 0, 80),
            'info_text'     => mb_substr(trim((string) ($data['rc_info_text'] ?? '')), 0, 800),
            'layout'        => array_key_exists($data['rc_layout'] ?? '', ShopSettingsModel::MENU_LAYOUTS) ? $data['rc_layout'] : 'lista',
        ];
    }

    /** Solo cuentas de Hello del propio tenant: nunca se confía en el id que llega del formulario. */
    /**
     * Campos de cada sucursal. Con aero/tracking instalado la ubicación se fija
     * en el mapa (FormWidget `locationpicker`, con botón de geolocalización);
     * sin él, lat/lng quedan como números.
     */
    protected function branchFormFields(): array
    {
        $fields = [
            'name'      => ['label' => 'Nombre', 'type' => 'text', 'span' => 'left'],
            'phone'     => ['label' => 'Teléfono', 'type' => 'text', 'span' => 'right'],
            'address'   => ['label' => 'Dirección', 'type' => 'text', 'span' => 'full'],
        ];

        if (class_exists(\Aero\Tracking\FormWidgets\LocationPicker::class)) {
            $fields['lat'] = ['label' => 'Ubicación exacta', 'type' => 'locationpicker', 'lngField' => 'lng', 'height' => 260, 'span' => 'full'];
            $fields['lng'] = ['label' => 'Longitud', 'type' => 'text', 'span' => 'full', 'containerAttributes' => ['style' => 'display:none']];
        } else {
            $fields['lat'] = ['label' => 'Latitud', 'type' => 'number', 'span' => 'left'];
            $fields['lng'] = ['label' => 'Longitud', 'type' => 'number', 'span' => 'right'];
        }

        $fields['is_active'] = ['label' => 'Activa', 'type' => 'switch', 'span' => 'left', 'default' => true];

        return $fields;
    }

    /** Normaliza las filas del repeater y asigna un id a cada sucursal. */
    protected function branchesFrom(array $data): array
    {
        $rows = [];
        $ids = [];

        foreach ((array) ($data['branches'] ?? []) as $row) {
            $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 120);
            if ($name === '') {
                continue;
            }

            $address = mb_substr(trim((string) ($row['address'] ?? '')), 0, 255);

            // Id estable a partir de nombre y dirección (sin campo oculto: no hay parcial _field_hidden).
            $id = substr(md5(mb_strtolower($name) . '|' . mb_strtolower($address)), 0, 8);
            while (in_array($id, $ids, true)) {
                $id = substr(md5($id . '|' . count($ids)), 0, 8);
            }
            $ids[] = $id;

            $lat = is_numeric($row['lat'] ?? null) ? round((float) $row['lat'], 6) : null;
            $lng = is_numeric($row['lng'] ?? null) ? round((float) $row['lng'], 6) : null;

            $rows[] = [
                'id'        => $id,
                'name'      => $name,
                'address'   => $address ?: null,
                'phone'     => mb_substr(trim((string) ($row['phone'] ?? '')), 0, 30) ?: null,
                'lat'       => $lat,
                'lng'       => $lng,
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
        $marketplaceOk     = ShopSettingsModel::marketplacePurchasedBy($tenantId);
        $config            = new \stdClass;
        $config->model     = $model;
        $config->arrayName = 'ShopSettings';
        $config->alias     = 'shopSettingsForm';
        $config->fields    = [
            'is_enabled' => [
                'label'   => 'Activar tienda',
                'type'    => 'switch',
                'span'    => 'full',
                'default' => false,
            ],
            'store_mode' => [
                'label'   => 'Tipo de tienda',
                'type'    => 'balloon-selector',
                'default' => 'standard',
                'options' => [
                    'standard' => 'Tienda estándar',
                    'whatsapp' => 'Tienda para WhatsApp',
                    'restaurant' => 'Restaurante',
                    'marketplace' => 'Marketplace',
                ],
                'comment' => ($marketplaceOk ? '' : 'Marketplace requiere contratar el servicio «Marketplace» (4 monedas de oro al mes) en el App Store. ') . 'La tienda para WhatsApp simplifica el checkout: solo pide el celular del cliente y envía el pedido al chat. Restaurante agrega extras por plato, tipo de pedido (local, recoger, delivery), mesas por QR y horarios. Marketplace convierte a miembros de tu equipo en comerciantes: cada uno vende sus productos, cobra en su cuenta y ve su balance.',
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
            'rc_layout' => [
                'label'   => 'Diseño de la carta',
                'type'    => 'balloon-selector',
                'default' => 'lista',
                'options' => ShopSettingsModel::MENU_LAYOUTS,
                'comment' => 'Cómo se ven los platos en tu página de la tienda (/tienda). Lista: una fila por plato con descripción y foto a la derecha. Tarjetas: cuadrícula con foto grande. Compacta: solo nombre y precio en dos columnas, sin fotos, ideal para cartas largas. El carrito y los pedidos funcionan igual en los tres.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'shipping_fee' => [
                'label' => 'Costo de envío', 'type' => 'number', 'span' => 'left', 'default' => 0,
                'comment' => 'Se cobra una vez por pedido con productos que requieren envío. 0 = envío gratis.',
                'trigger' => ['action' => 'hide', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
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
            'rc_qr_dine_in' => [
                'label' => 'Cobros con QR para comensales en el local', 'type' => 'switch', 'default' => true,
                'comment' => 'Si está activo, quien come en el local puede elegir «Pago QR» al pedir y esperar con su QR. Apagado, en el local solo se paga en efectivo en caja (el QR sigue disponible para recoger y delivery).',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_panic_button' => [
                'label' => 'Botón de emergencia para comensales en el local', 'type' => 'switch', 'default' => true,
                'comment' => 'Muestra el botón «Emergencia» en la página del pedido de quien come en el local (pide confirmar antes de enviar). Al usarlo, la cocina y la caja ven una franja con el número de mesa hasta que alguien la marca como «Atendida». Apagado, el botón no aparece; los avisos que ya estén activos se siguen mostrando hasta atenderlos.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_hello_account_id' => [
                'label'       => 'Cuenta de Hello del local',
                'type'        => 'dropdown',
                'options'     => $this->whatsappAccountOptions($tenantId),
                'placeholder' => 'Usar la cuenta de WhatsApp por defecto',
                'comment'     => 'Con esta cuenta de WhatsApp el local avisa a sus clientes (pedido recibido, listo, etc.) y envía recibos desde la caja. Se administran en Hello → Cuentas.',
                'trigger'     => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_wifi_name' => [
                'label' => 'Red WiFi del local', 'type' => 'text', 'span' => 'left',
                'comment' => 'Opcional. Se muestra a quien come en el local mientras espera su pedido.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_wifi_password' => [
                'label' => 'Clave del WiFi', 'type' => 'text', 'span' => 'right',
                'comment' => 'Es una clave para tus clientes: quien abra la página de su pedido en el local la verá. Vacía = no se muestra.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'rc_info_text' => [
                'label' => 'Promociones e información de espera', 'type' => 'textarea', 'size' => 'small',
                'comment' => 'Texto que ven los clientes mientras esperan su pedido (promos, horarios, avisos). Una línea por mensaje.',
                'trigger' => ['action' => 'show', 'field' => 'store_mode', 'condition' => 'value[restaurant]'],
            ],
            'feedback_mode' => [
                'label'   => 'Opiniones de los clientes',
                'type'    => 'dropdown',
                'default' => 'off',
                'options' => ShopSettingsModel::FEEDBACK_MODES,
                'comment' => 'Al terminar su pedido el cliente puede dejar estrellas y un comentario. Elige si quedan solo para ti, se publican en la tienda o se publican cuando las apruebas (Tienda → Opiniones).',
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
                    'fields' => $this->branchFormFields(),
                ],
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
