<?php namespace Aero\Shop\Classes;

class PaymentGatewayManager
{
    protected array $drivers = [];

    public function register(string $code, string $class): void
    {
        $this->drivers[$code] = $class;
    }

    public function listDrivers(): array
    {
        $options = [];
        foreach ($this->drivers as $code => $class) {
            $options[$code] = $class::label();
        }
        return $options;
    }

    public function driver(string $code): ?string
    {
        return $this->drivers[$code] ?? null;
    }
}
