<?php namespace Aero\Crm\Controllers;

use Aero\Crm\Models\CrmSettings as CrmSettingsModel;
use Aero\Crm\Models\Pipeline;
use Aero\Sites\Traits\ResolvesCurrentTenant;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use BackendMenu;
use Flash;

class CrmSettings extends Controller
{
    use ResolvesCurrentTenant;

    public $requiredPermissions = ['aero.crm.manage_settings'];

    public ?Form $settingsWidget = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Crm', 'crm', 'crm-configuracion');
    }

    public function index()
    {
        $this->pageTitle = 'Configuración del CRM';
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            $this->vars['noTenant'] = true;
            return;
        }

        $settings = CrmSettingsModel::firstOrCreate(['tenant_id' => $tenant->id], ['is_enabled' => false]);

        $this->settingsWidget = $this->makeSettingsWidget($settings);
        $this->vars['tenant'] = $tenant;
    }

    public function onSave()
    {
        $tenant   = $this->getCurrentTenant();
        $settings = CrmSettingsModel::firstOrCreate(['tenant_id' => $tenant->id]);
        $wasEnabled = $settings->is_enabled;
        $data = post('CrmSettings', []);

        $settings->is_enabled = (bool) ($data['is_enabled'] ?? false);
        $settings->collections_enabled = (bool) ($data['collections_enabled'] ?? false);
        $settings->reminder_interval_days = (int) ($data['reminder_interval_days'] ?? 3) ?: 3;
        $settings->reminder_message_template = $data['reminder_message_template'] ?? null;
        $settings->save();

        if (!$wasEnabled && $settings->is_enabled) {
            Pipeline::seedDefaultForTenant($tenant->id);
        }

        Flash::success('Configuración del CRM guardada.');
        return [];
    }

    protected function makeSettingsWidget(CrmSettingsModel $model): Form
    {
        $config            = new \stdClass;
        $config->model     = $model;
        $config->arrayName = 'CrmSettings';
        $config->alias     = 'crmSettingsForm';
        $config->fields    = [
            'is_enabled' => [
                'label'   => 'CRM activado',
                'type'    => 'switch',
                'span'    => 'full',
                'default' => false,
                'comment' => 'Al activarlo se crea el pipeline de ventas por defecto para este tenant.',
            ],
            'collections_enabled' => [
                'label'   => 'Recordatorios automáticos de cobranza activados',
                'type'    => 'switch',
                'span'    => 'full',
                'default' => true,
                'comment' => 'Corta general del módulo. Los pasos de la cascada (cuándo y qué enviar) se configuran en Cobranzas → Automatización.',
            ],
            'reminder_interval_days' => [
                'label'   => 'Días entre recordatorios (modo simple)',
                'type'    => 'number',
                'span'    => 'auto',
                'default' => 3,
                'comment' => 'Solo se usa si en Automatización no hay ninguna regla activa: repite un único recordatorio cada tantos días mientras el cobro siga vencido.',
            ],
            'reminder_message_template' => [
                'label'   => 'Plantilla del mensaje (modo simple / por defecto)',
                'type'    => 'textarea',
                'size'    => 'small',
                'span'    => 'full',
                'placeholder' => 'Hola {{contacto}}, te recordamos que tienes un pago pendiente de {{monto}} {{moneda}} por "{{concepto}}" con vencimiento el {{vencimiento}}. Por favor regulariza a la brevedad.',
                'comment' => 'Se usa en el modo simple y como respaldo de cualquier regla de Automatización que no tenga su propia plantilla. Variables: {{contacto}}, {{monto}}, {{moneda}}, {{concepto}}, {{vencimiento}}.',
            ],
        ];

        $widget = $this->makeWidget(Form::class, $config);
        $widget->bindToController();
        return $widget;
    }
}
