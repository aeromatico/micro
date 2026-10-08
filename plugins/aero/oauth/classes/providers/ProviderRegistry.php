<?php namespace Aero\Oauth\Classes\Providers;

use Event;

class ProviderRegistry
{
    /** @var array<string, ProviderInterface>|null */
    protected static ?array $providers = null;

    public static function all(): array
    {
        if (self::$providers === null) {
            self::$providers = ['google' => new GoogleProvider()];

            // Otros plugins (o futuros proveedores) se registran por evento.
            foreach (array_filter(Event::dispatch('aero.oauth.registerProviders') ?: []) as $extra) {
                foreach ((array) $extra as $provider) {
                    if ($provider instanceof ProviderInterface) {
                        self::$providers[$provider->code()] = $provider;
                    }
                }
            }
        }

        return self::$providers;
    }

    public static function get(string $code): ?ProviderInterface
    {
        return self::all()[$code] ?? null;
    }

    public static function callbackUrl(string $code): string
    {
        return url('aero/oauth/' . $code . '/callback');
    }

    public static function flush(): void
    {
        self::$providers = null;
    }
}
