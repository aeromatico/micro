<?php namespace Aero\Connector\Controllers;

use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Models\Connector;
use Backend\Classes\Controller;
use BackendMenu;

class Connectors extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.connector.manage_connectors'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Connector', 'connector', 'connectors');
    }

    /**
     * `config_json`/`credentials_json` son campos virtuales (JSON plano en un
     * textarea) para no atarse a un formulario dinámico por tipo de conector.
     */
    public function formBeforeSave($model): void
    {
        $model->config = $this->decodeJsonField(post('Connector.config_json'));
        $model->credentials = $this->decodeJsonField(post('Connector.credentials_json'));
    }

    protected function decodeJsonField(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        return json_decode($raw, true) ?? [];
    }

    public function onTestConnector()
    {
        $connector = Connector::findOrFail(post('record_id'));
        $payload = $this->decodeJsonField(post('test_payload'));

        $result = app(ConnectorClient::class)->test($connector, $payload)->toArray();

        return [
            '#ConnectorTestResult' => $this->makePartial(
                '~/plugins/aero/connector/models/connector/_test_result.htm',
                ['result' => $result]
            ),
        ];
    }
}
