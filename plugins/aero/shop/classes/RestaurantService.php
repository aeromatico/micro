<?php namespace Aero\Shop\Classes;

use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Models\ModifierGroup;
use Aero\Shop\Models\Product;

/**
 * Extras de un plato: valida lo elegido por el cliente contra los grupos
 * reales del producto (mín/máx, opciones activas) y calcula el sobreprecio.
 * Los precios salen SIEMPRE de la BD, nunca de lo que envía el navegador.
 */
class RestaurantService
{
    /**
     * @param string[] $uids uids de opciones elegidas
     * @return array{snapshot: array, uids: array, delta: float}
     * @throws OrderException
     */
    public static function resolve(Product $product, array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('strval', $uids))));
        $groups = ModifierGroup::forTenant($product->tenant_id)->where('product_id', $product->id)->orderBy('sort_order')->get();

        $snapshot = [];
        $delta = 0.0;
        $matched = [];

        foreach ($groups as $group) {
            $chosen = [];
            foreach ($group->activeChoices() as $uid => $choice) {
                if (in_array($uid, $uids, true)) {
                    $chosen[] = $choice;
                    $matched[] = $uid;
                }
            }

            if (count($chosen) < $group->min_select) {
                throw new OrderException('En "' . $product->name . '" elige ' . ($group->min_select > 1 ? 'al menos ' . $group->min_select . ' opciones de' : 'una opción de') . ' "' . $group->name . '".');
            }
            if (count($chosen) > $group->max_select) {
                throw new OrderException('En "' . $product->name . '" puedes elegir máximo ' . $group->max_select . ' de "' . $group->name . '".');
            }

            foreach ($chosen as $c) {
                $delta += (float) $c['price_delta'];
                $snapshot[] = ['group' => $group->name, 'uid' => $c['uid'], 'name' => $c['name'], 'price' => (float) $c['price_delta']];
            }
        }

        if (array_diff($uids, $matched)) {
            throw new OrderException('Un extra de "' . $product->name . '" ya no está disponible.');
        }

        return ['snapshot' => $snapshot, 'uids' => $matched, 'delta' => round($delta, 4)];
    }

    /** Clave estable de la línea: mismo plato + mismos extras + misma nota = una línea. */
    public static function suffix(array $uids, ?string $note): string
    {
        sort($uids);
        $note = trim((string) $note);

        return ($uids || $note !== '') ? substr(md5(json_encode($uids) . '|' . $note), 0, 8) : '';
    }

    public static function cleanNote(?string $note): ?string
    {
        $note = trim(preg_replace('/\s+/', ' ', (string) $note));

        return $note === '' ? null : mb_substr($note, 0, 255);
    }

    /** Texto corto de los extras de una línea: "Grande, Extra queso". */
    public static function modifiersText(?array $snapshot): string
    {
        return implode(', ', array_column((array) $snapshot, 'name'));
    }
}
