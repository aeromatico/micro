<?php namespace Aero\Connector\Controllers;

use Event;
use Aero\Connector\Models\ConnectorLog;
use Aero\Connector\Models\WebhookEndpoint;
use Backend\Classes\Controller;
use BackendMenu;

class WebhookEndpoints extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.connector.manage_webhooks'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Connector', 'connector', 'webhookendpoints');
    }

    /**
     * `secret` es un campo virtual (password) — solo se re-cifra si el admin
     * escribió algo nuevo, para no borrar el secreto existente al editar.
     */
    public function formBeforeSave($model): void
    {
        $secret = post('WebhookEndpoint.secret');

        if ($secret !== null && $secret !== '') {
            $model->secret = $secret;
        }
    }

    /**
     * Pasa un payload de prueba por el mismo pipeline que un webhook real
     * (log + evento), sin exigir la firma del proveedor — para poder
     * verificar el wire-up del evento interno sin esperar al tercero real.
     */
    public function onSimulateWebhook()
    {
        $endpoint = WebhookEndpoint::findOrFail(post('record_id'));
        $payload = json_decode((string) post('payload'), true) ?? [];

        ConnectorLog::create([
            'direction'           => 'in',
            'webhook_endpoint_id' => $endpoint->id,
            'request_payload'     => $payload,
            'response_payload'    => null,
            'status_code'         => 200,
            'is_test'             => true,
        ]);

        Event::dispatch($endpoint->dispatch_event, [$endpoint, $payload, null]);

        return [
            '#WebhookDeliveriesList' => $this->makePartial(
                '~/plugins/aero/connector/models/webhookendpoint/_deliveries_list.htm',
                ['endpoint' => $endpoint]
            ),
        ];
    }
}
