<?php namespace Aero\Help\Classes;

use Illuminate\Support\Facades\Schema;

/**
 * Dependencias blandas: otro plugin cuenta solo si su clase existe y su tabla
 * también (el plugin puede estar presente pero sin migrar, y en las pruebas
 * aisladas solo se migra el plugin bajo prueba).
 */
class Integrations
{
    protected static array $memo = [];

    public static function docs(): bool
    {
        return self::check('docs', \Aero\Docs\Models\Article::class, ['aero_docs_articles', 'aero_docs_guides']);
    }

    public static function services(): bool
    {
        return self::check('services', \Aero\Services\Models\Service::class, ['aero_services_services']);
    }

    /** Para pruebas: olvida lo comprobado. */
    public static function flush(): void
    {
        self::$memo = [];
    }

    protected static function check(string $key, string $class, array $tables): bool
    {
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        $ok = class_exists($class);
        foreach ($tables as $table) {
            $ok = $ok && Schema::hasTable($table);
        }

        return self::$memo[$key] = $ok;
    }
}
