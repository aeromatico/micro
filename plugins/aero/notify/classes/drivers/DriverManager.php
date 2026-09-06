<?php namespace Aero\Notify\Classes\Drivers;

use Event;

/**
 * Registro de drivers de canal. Trae 'email' y 'whatsapp' por defecto (ver
 * Plugin::boot()) y queda abierto a que otro plugin registre canales nuevos
 * (sms, telegram, webhook) vía el evento aero.notify.registerChannelDrivers,
 * igual que Aero.Hello hace con sus drivers de mensajería.
 */
class DriverManager
{
    protected array $drivers = [];
    protected bool $booted = false;

    protected function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Event::fire('aero.notify.registerChannelDrivers', [$this]);
        $this->booted = true;
    }

    public function register(string $channel, string $class): void
    {
        $this->drivers[$channel] = $class;
    }

    public function has(string $channel): bool
    {
        $this->boot();

        return isset($this->drivers[$channel]);
    }

    public function make(string $channel): ChannelDriverInterface
    {
        $this->boot();

        $class = $this->drivers[$channel] ?? null;

        if (!$class) {
            throw new \RuntimeException("Canal de notificación '{$channel}' no tiene driver registrado.");
        }

        return new $class();
    }
}
