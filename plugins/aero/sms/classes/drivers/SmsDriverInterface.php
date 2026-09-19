<?php namespace Aero\Sms\Classes\Drivers;

use Aero\Sms\Models\Message;

interface SmsDriverInterface
{
    public function code(): string;

    /**
     * Entrega el mensaje al proveedor.
     *
     * @throws SmsDriverException si el proveedor lo rechaza o no responde.
     */
    public function send(Message $message): DriverResult;
}
