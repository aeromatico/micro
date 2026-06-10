<?php namespace Aero\Sites\Classes\Niches;

use Event;

class NicheManager
{
    protected array $drivers = [];
    protected bool $booted = false;

    protected function boot(): void
    {
        if ($this->booted) return;
        Event::fire('aero.sites.registerNiches', [$this]);
        $this->booted = true;
    }

    public function register(string $handle, string $class): void
    {
        $this->drivers[$handle] = $class;
    }

    public function make(string $handle): NicheManagerInterface
    {
        $this->boot();
        $class = $this->drivers[$handle] ?? $this->drivers['generic'] ?? null;
        if (!$class) {
            throw new \RuntimeException("Niche driver '{$handle}' not registered.");
        }
        return new $class();
    }

    public function all(): array
    {
        $this->boot();
        $result = [];
        foreach ($this->drivers as $handle => $class) {
            $niche = new $class();
            $result[$handle] = $niche->getName();
        }
        return $result;
    }

    public function options(): array
    {
        return $this->all();
    }
}
