<?php namespace Aero\Chat\Classes;

use Aero\Hello\Models\Conversation;

/**
 * Ubicaciones que el cliente compartió en una conversación, de la más reciente
 * a la más antigua. Las coordenadas viven en el cuerpo del mensaje
 * ("📍 (lat, lng)" + nombre/dirección opcional). Coordenadas repetidas se
 * agrupan en la más reciente.
 */
class SharedLocations
{
    public static function for(Conversation $conversation, int $limit = 20): array
    {
        $seen = [];
        $out = [];

        $messages = $conversation->messages()
            ->where('direction', 'inbound')->where('type', 'location')
            ->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get();

        foreach ($messages as $m) {
            if (!preg_match('/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/', (string) $m->body, $r)) {
                continue;
            }

            [$lat, $lng] = [(float) $r[1], (float) $r[2]];
            $key = round($lat, 5) . ',' . round($lng, 5);

            if (abs($lat) > 90 || abs($lng) > 180) {
                continue;
            }

            $label = trim((string) preg_replace('/^[^\n]*\n?/u', '', (string) $m->body));

            // Misma ubicación repetida: se conserva la más reciente, pero hereda el nombre si solo el envío antiguo lo traía.
            if (isset($seen[$key])) {
                if ($label !== '' && $out[$seen[$key]]['label'] === null) {
                    $out[$seen[$key]]['label'] = mb_substr($label, 0, 120);
                }
                continue;
            }
            $seen[$key] = count($out);

            $out[] = [
                'id'    => $m->id,
                'lat'   => $lat,
                'lng'   => $lng,
                'label' => $label !== '' ? mb_substr($label, 0, 120) : null,
                'url'   => "https://www.google.com/maps?q={$lat},{$lng}",
                'at'    => optional($m->created_at)->toIso8601String(),
            ];

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
