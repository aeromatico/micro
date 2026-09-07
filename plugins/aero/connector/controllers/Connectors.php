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
     * `config_json`/`credentials_json` siguen siendo JSON plano (avanzado),
     * pero "API Key"/"Secret"/"Modelo IA" son inputs directos que se mezclan
     * encima de lo que venga en el JSON avanzado.
     */
    public function formBeforeSave($model): void
    {
        $advancedConfig = $this->decodeJsonField(post('Connector.config_json'));
        $model->config = array_filter(
            array_merge($advancedConfig, [
                'model' => post('Connector.ai_model') ?: null,
            ]),
            fn ($value) => $value !== null && $value !== ''
        );

        $advancedCredentials = $this->decodeJsonField(post('Connector.credentials_json'));
        $model->credentials = array_filter(
            array_merge($advancedCredentials, [
                'api_key' => post('Connector.api_key') ?: null,
                'secret'  => post('Connector.secret') ?: null,
            ]),
            fn ($value) => $value !== null && $value !== ''
        );
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
