<?php namespace Aero\Pos\Controllers;

use Aero\Pos\Classes\PosProvisioner;
use Aero\Pos\Models\PosSettings;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;

class Settings extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.pos.manage'];

    public ?Form $settingsWidget = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Pos', 'pos', 'pos-configuracion');
    }

    public function index()
    {
        $this->pageTitle = 'Configuración del punto de venta';
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            $this->vars['noTenant'] = true;
            return;
        }

        $settings = PosProvisioner::ensureDefaults($tenant->id);
        $this->settingsWidget = $this->makeSettingsWidget($settings);
        $this->vars['tenant'] = $tenant;
        $this->vars['shopEnabled'] = (bool) \Aero\Shop\Models\ShopSettings::where('tenant_id', $tenant->id)->value('is_enabled');
    }

    public function onSave()
    {
        $tenant = $this->getCurrentTenant();
        $settings = PosProvisioner::ensureDefaults($tenant->id);
        $data = post('PosSettings', []);

        $profile = array_key_exists($data['profile'] ?? '', PosSettings::PROFILES) ? $data['profile'] : $settings->profile;
        if ($profile !== $settings->profile) {
            // Al cambiar de perfil se aplican sus módulos recomendados; luego cada uno se puede ajustar.
            $settings->applyPreset($profile);
        } else {
            foreach (array_keys(PosSettings::PRESETS['restaurant']) as $flag) {
                $settings->{$flag} = (bool) ($data[$flag] ?? false);
            }
        }

        $tips = array_filter(array_map('trim', explode(',', (string) ($data['tip_presets'] ?? ''))), fn ($v) => is_numeric($v) && $v >= 0 && $v <= 100);

        $settings->fill([
            'tip_presets'            => $tips ? implode(',', $tips) : '0,5,10',
            'discount_limit_percent' => min(100, max(0, (int) ($data['discount_limit_percent'] ?? 10))),
            'require_shift'          => (bool) ($data['require_shift'] ?? false),
            'ticket_width'           => ($data['ticket_width'] ?? '80') === '58' ? 58 : 80,
            'ticket_header'          => trim((string) ($data['ticket_header'] ?? '')) ?: null,
            'ticket_footer'          => trim((string) ($data['ticket_footer'] ?? '')) ?: null,
        ]);
        $settings->save();

        Flash::success('Configuración del punto de venta guardada.');

        return \Redirect::refresh();
    }

    protected function makeSettingsWidget(PosSettings $model): Form
    {
        $config = new \stdClass;
        $config->model = $model;
        $config->arrayName = 'PosSettings';
        $config->alias = 'posSettingsForm';
        $config->fields = [
            'profile' => [
                'label'   => 'Tipo de negocio',
                'type'    => 'balloon-selector',
                'options' => PosSettings::PROFILES,
                'comment' => 'Al cambiarlo se activan los módulos recomendados para ese tipo de negocio. Después puedes ajustarlos uno por uno.',
            ],
            '_modules' => ['label' => 'Módulos de la app de venta', 'type' => 'section'],
            'feat_tables'      => ['label' => 'Mesas', 'type' => 'switch', 'span' => 'left', 'comment' => 'Mapa de mesas y cuentas por mesa.'],
            'feat_tabs'        => ['label' => 'Cuentas abiertas', 'type' => 'switch', 'span' => 'right', 'comment' => 'Agregar platos a una cuenta y cobrar al final.'],
            'feat_kitchen'     => ['label' => 'Enviar a cocina', 'type' => 'switch', 'span' => 'left', 'comment' => 'Las comandas llegan a la pantalla de Cocina.'],
            'feat_tips'        => ['label' => 'Propina', 'type' => 'switch', 'span' => 'right'],
            'feat_barcode'     => ['label' => 'Código de barras', 'type' => 'switch', 'span' => 'left', 'comment' => 'Agregar productos escaneando.'],
            'feat_free_amount' => ['label' => 'Venta libre (monto abierto)', 'type' => 'switch', 'span' => 'right', 'comment' => 'Cobrar un monto sin producto del catálogo.'],
            '_cash' => ['label' => 'Caja y descuentos', 'type' => 'section'],
            'require_shift' => ['label' => 'Exigir turno de caja abierto para vender', 'type' => 'switch', 'span' => 'left'],
            'discount_limit_percent' => [
                'label' => 'Descuento máximo sin autorización (%)', 'type' => 'number', 'span' => 'right',
                'comment' => 'Por encima se pide el PIN de un supervisor.',
            ],
            'tip_presets' => ['label' => 'Propinas sugeridas (%)', 'type' => 'text', 'span' => 'left', 'comment' => 'Separadas por coma. Ej: 0,5,10'],
            '_ticket' => ['label' => 'Ticket', 'type' => 'section'],
            'ticket_width' => [
                'label' => 'Ancho del papel', 'type' => 'balloon-selector', 'span' => 'left',
                'options' => ['58' => '58 mm', '80' => '80 mm'],
            ],
            'ticket_header' => ['label' => 'Encabezado', 'type' => 'textarea', 'size' => 'small', 'span' => 'left', 'comment' => 'Nombre, dirección, teléfono…'],
            'ticket_footer' => ['label' => 'Pie', 'type' => 'textarea', 'size' => 'small', 'span' => 'right', 'comment' => 'Ej: ¡Gracias por su visita!'],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();

        return $widget;
    }
}
