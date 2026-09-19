<?php namespace Aero\Sms\Classes\Drivers;

use Aero\Sms\Models\Message;
use Str;

/**
 * No envía nada: sirve para probar API, cobros y colas sin gastar un centavo.
 * Los números terminados en 0000 simulan un fallo.
 */
class SimulatedDriver implements SmsDriverInterface
{
    public function code(): string
    {
        return 'simulated';
    }

    public function send(Message $message): DriverResult
    {
        if (str_ends_with($message->to, '0000')) {
            throw new SmsDriverException('Fallo simulado.', 'SIM0000');
        }

        return new DriverResult('SIM' . Str::upper(Str::random(20)), 'delivered');
    }
}
