<?php namespace Aero\Services\Http\Controllers;

use Aero\Services\Classes\PublicMenu;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Menú público de servicios para sitios de la misma plataforma (p. ej. el
 * navbar de boliviahost.com). Solo lectura y solo datos que ya son públicos
 * en el megamenú: sin autenticación, sin campos internos.
 */
class MenuController extends Controller
{
    public const CACHE_KEY = 'aero.services.public_menu';
    public const CACHE_SECONDS = 600;

    public function index()
    {
        $groups = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return array_map(function (array $group) {
                unset($group['order']);

                return $group;
            }, PublicMenu::groups(self::publicBase()));
        });

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
