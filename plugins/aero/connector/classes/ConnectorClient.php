<?php namespace Aero\Connector\Classes;

use Aero\Connector\Models\Connector;
use Aero\Connector\Models\ConnectorLog;
use Event;

/**
 * Punto único para ejecutar un Connector: resuelve su driver por tipo,
 * corre la petición y deja registro en ConnectorLog. Reemplaza los wrappers
 * sueltos de `Http::` que cada plugin venía escribiendo por su cuenta.
 */
class ConnectorClient
{
    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        return $this->run($connector, $payload, isTest: false);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        return $this->run($connector, $overridePayload, isTest: true);
    }

    protected function run(Connector $connector, array $payload, bool $isTest): ConnectorResponse
    {
        $driver = TypeRegistry::driverFor($connector->type);

        if (!$driver) {
            $response = ConnectorResponse::fromError("Tipo de conector desconocido: {$connector->type}");
        }
        else {
            $response = $isTest
                ? $driver->test($connector, $payload)
                : $driver->send($connector, $payload);
        }

        ConnectorLog::create([
            'direction'        => 'out',
            'connector_id'     => $connector->id,
            'request_payload'  => $payload,
            'response_payload' => $response->toArray(),
            'status_code'      => $response->statusCode,
            'duration_ms'      => $response->durationMs,
            'is_test'          => $isTest,
        ]);

        // Evento genérico, reutilizable más allá de créditos (métricas,
        // billing propio, etc.) — no se dispara en pruebas del tester.
        if (!$isTest) {
            Event::fire('aero.connector.afterRun', [$connector, $response]);
        }

        return $response;
    }
}
