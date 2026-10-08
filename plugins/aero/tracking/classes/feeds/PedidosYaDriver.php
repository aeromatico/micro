<?php namespace Aero\Tracking\Classes\Feeds;

use Aero\Tracking\Models\Feed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Enlace «compartir pedido» de PedidosYa. La web pública no usa websocket:
 * hace polling a dos endpoints JSON con una cookie de sesión (5 h) que se
 * obtiene al abrir la página compartida. Es una API interna sin documentar:
 * todo se lee defensivamente con data_get().
 */
class PedidosYaDriver implements FeedDriver
{
    protected const HOST = 'https://web-apps.pedidosya.com';

    public function key(): string
    {
        return 'pedidosya';
    }

    public function label(): string
    {
        return 'PedidosYa';
    }

    public function match(string $url): ?array
    {
        $re = '#^https://web-apps\.pedidosya\.com/(shared-order-state|compartir-pedido)/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/?(?:[?\#].*)?$#i';

        if (!preg_match($re, $url, $m)) {
            return null;
        }

        $id = strtolower($m[2]);

        return ['external_id' => $id, 'url' => self::HOST . "/{$m[1]}/{$id}"];
    }

    public function poll(Feed $feed): array
    {
        $cookie = $this->cookie($feed);

        $tracking = $this->get($cookie, '/order-status/api/share/order-tracking');
        if ($tracking->status() === 401) {
            $cookie = $this->bootstrap($feed);
            $tracking = $this->get($cookie, '/order-status/api/share/order-tracking');
        }

        if (in_array($tracking->status(), [401, 403, 404, 410], true)) {
            throw new FeedGoneException('PedidosYa ya no entrega datos de este enlace (HTTP ' . $tracking->status() . ').');
        }
        if (!$tracking->ok()) {
            throw new \RuntimeException('PedidosYa respondió HTTP ' . $tracking->status());
        }

        $t = $tracking->json();
        $s = [
            'phase'       => $this->phase((array) data_get($t, 'state', [])),
            'external_id' => (string) data_get($t, 'orderId', ''),
            'title'       => trim(data_get($t, 'orderDetails.vendorName', '') . ' · ' . data_get($t, 'orderDetails.productsLabel', ''), ' ·'),
            'eta_text'    => data_get($t, 'progress.orderTrackingRevampV2.eta.value'),
            'delay_label' => data_get($t, 'progress.orderTrackingRevampV2.eta.title.label'),
            'message'     => data_get($t, 'progress.orderTrackingRevampV2.orderState.message'),
            'lat' => null, 'lng' => null, 'position_at' => null,
            'origin' => null, 'destination' => null, 'interval' => 10,
        ];

        // La ubicación puede dejar de existir al terminar el pedido: no es motivo de error.
        $loc = $this->get($cookie, '/order-status/api/share/order-location');
        if ($loc->ok()) {
            $l = $loc->json();
            $s['lat'] = data_get($l, 'rider.last_location.latitude');
            $s['lng'] = data_get($l, 'rider.last_location.longitude');
            $ts = data_get($l, 'rider.last_location.timestamp');
            $s['position_at'] = $ts ? Carbon::parse($ts) : null;
            $s['origin'] = $this->point(data_get($l, 'waypoints.origin'));
            $s['destination'] = $this->point(data_get($l, 'waypoints.destination'));
            $s['interval'] = max(5, (int) (data_get($l, 'peya_config.update_rate_millis', 10000) / 1000));
        }

        return $s;
    }

    /**
     * pickedUp y nearOriginOrAfter son semánticas inferidas de un pedido real;
     * delivered/cancelled son explícitas.
     */
    protected function phase(array $st): string
    {
        return match (true) {
            !empty($st['cancelled'])        => 'cancelled',
            !empty($st['delivered'])        => 'delivered',
            !empty($st['pickedUp'])         => 'on_the_way',
            !empty($st['nearOriginOrAfter']) => 'at_origin',
            !empty($st['confirmed'])        => 'confirmed',
            default                         => 'queued',
        };
    }

    protected function point($p): ?array
    {
        $lat = data_get($p, 'latitude');
        $lng = data_get($p, 'longitude');

        return is_numeric($lat) && is_numeric($lng) ? [(float) $lat, (float) $lng] : null;
    }

    protected function get(string $cookie, string $path)
    {
        return Http::timeout(15)->withHeaders([
            'Cookie' => $cookie, 'Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 (compatible; AeroTracking/1.0)',
        ])->get(self::HOST . $path);
    }

    protected function cookie(Feed $feed): string
    {
        if ($feed->session_state) {
            try {
                return Crypt::decryptString($feed->session_state);
            } catch (\Throwable) {
                // sesión ilegible: se renueva
            }
        }

        return $this->bootstrap($feed);
    }

    /** Abre la página compartida para que el servidor asocie la sesión a la orden. */
    protected function bootstrap(Feed $feed): string
    {
        $page = Http::timeout(15)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AeroTracking/1.0)'])->get($feed->url);
        if (!$page->ok()) {
            throw new \RuntimeException('No se pudo abrir el enlace de PedidosYa (HTTP ' . $page->status() . ').');
        }

        $pairs = [];
        foreach ($page->toPsrResponse()->getHeader('Set-Cookie') as $line) {
            $pairs[] = trim(explode(';', $line, 2)[0]);
        }
        $cookie = implode('; ', $pairs);

        $feed->session_state = Crypt::encryptString($cookie);
        if ($feed->exists) {
            $feed->newQuery()->whereKey($feed->id)->update(['session_state' => $feed->session_state]);
        }

        return $cookie;
    }
}
