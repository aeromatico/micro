<?php namespace Aero\Connector\Contracts;

use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Models\Connector;

interface ConnectorDriver
{
    /**
     * Envía una petición real usando la configuración/credenciales del conector.
     * $payload es un array libre que el llamador arma según el tipo (ej. mensaje de chat, body de webhook saliente).
     */
    public function send(Connector $connector, array $payload = []): ConnectorResponse;

    /**
     * Ejecuta una llamada mínima de prueba contra el proveedor (usada por el tester del backend).
     */
    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse;
}
