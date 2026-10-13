<?php namespace Aero\Services\Http\Controllers;

use Aero\Services\Classes\LucideIcon;
use Aero\Services\Classes\PublicMenu;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Menú público de servicios para sitios de la misma plataforma (p. ej. el
 * navbar de boliviahost.com). Solo lectura y solo datos que ya son públicos
 * en el megamenú: sin autenticación, sin campos internos.
 *
 * ?exclude=rubros,otra deja fuera las categorías con esos slugs. Se aplica
 * DESPUÉS de leer la caché, que guarda siempre la lista completa (así el
 * guardado de un servicio la invalida una sola vez).
 *
 * ?icons=svg agrega `icon_svg` (SVG en línea del ícono Lucide) a cada servicio,
 * para consumidores cuyo bundle no trae el set completo de Lucide. Solo se
 * guarda el NOMBRE del ícono; el SVG se genera al vuelo (LucideIcon).
 */
class MenuController extends Controller
{
    public const CACHE_KEY = 'aero.services.public_menu';
    public const CACHE_SECONDS = 600;

    public function index(Request $request)
    {
        $groups = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return array_map(function (array $group) {
                unset($group['order']);

                return $group;
            }, PublicMenu::groups(self::publicBase()));
        });

        $exclude = collect(explode(',', strtolower((string) $request->query('exclude', ''))))
            ->map(fn ($slug) => preg_replace('/[^a-z0-9-]/', '', $slug))
            ->filter()
            ->take(20)
            ->all();

        if ($exclude) {
            $groups = array_values(array_filter($groups, fn ($g) => !in_array($g['slug'] ?? null, $exclude, true)));
        }

        if ($request->query('icons') === 'svg') {
            foreach ($groups as &$group) {
                foreach ($group['items'] as &$item) {
                    $item['icon_svg'] = LucideIcon::svg($item['icon'] ?? null, 'lucide');
                }
                unset($item);
            }
            unset($group);
        }

        return response()
            ->json(['data' => ['base_url' => self::publicBase(), 'groups' => $groups]])
            ->header('Cache-Control', 'public, max-age=300');
    }

    /** Dominio público de Market, donde viven /plugin/{slug} y /plugins/{categoría}. */
    public static function publicBase(): string
    {
        return rtrim((string) config('aero.services.public_url', 'https://market.com.bo'), '/');
    }
}
