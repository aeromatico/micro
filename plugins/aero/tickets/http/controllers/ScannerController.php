<?php namespace Aero\Tickets\Http\Controllers;

use Aero\Tickets\Classes\AccessControl;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Scanner;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Escáner móvil de puerta. Se entra con el enlace secreto del escáner (sin cuenta del panel). El tenant sale del host,
 * el escáner solo valida los eventos que se le asignaron y cada lectura se registra con su nombre.
 */
class ScannerController extends Controller
{
    protected function scanner(string $token): Scanner
    {
        $tenant = class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost()) : null;
        abort_unless($tenant, 404);

        $s = Scanner::findByToken($token, (int) $tenant->id);
        abort_unless($s, 404);

        // «Visto por última vez» (como máximo una escritura por minuto).
        if (!$s->last_seen_at || $s->last_seen_at->diffInSeconds(now(), true) > 60) {
            Scanner::whereKey($s->id)->update(['last_seen_at' => now(), 'last_seen_agent' => mb_substr((string) request()->userAgent(), 0, 120)]);
        }

        return $s;
    }

    protected function json(array $data, int $status = 200)
    {
        return response()->json($data, $status, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    protected function eventFor(Scanner $s, $id): ?Event
    {
        return Event::where('tenant_id', $s->tenant_id)->whereKey((int) $id)->first();
    }

    public function page(string $token)
    {
        $s = $this->scanner($token);

        return response()->view('aerotickets::scanner', ['scanner' => $s, 'events' => $s->allowedEvents(), 'token' => $token])
            ->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function validateScan(Request $r, string $token)
    {
        $s = $this->scanner($token);
        $d = $r->validate(['event_id' => 'required|integer', 'scan' => 'required|string|max:200', 'method' => 'nullable|in:qr,code,manual']);
        $event = $this->eventFor($s, $d['event_id']);
        if (!$event) {
            return $this->json(['allowed' => false, 'result' => 'not_allowed', 'title' => 'ACCESO DENEGADO', 'message' => 'Evento no disponible para este escáner.'], 403);
        }

        return $this->json(AccessControl::validate($d['scan'], $event, AccessControl::scannerOperator($s), $d['method'] ?? 'qr', $s));
    }

    public function search(Request $r, string $token)
    {
        $s = $this->scanner($token);
        $event = $this->eventFor($s, $r->query('event_id'));
        if (!$event || !$s->canScan($event)) {
            return $this->json(['error' => 'not_allowed'], 403);
        }

        return $this->json(AccessControl::search($event, (string) $r->query('q')));
    }

    public function stats(Request $r, string $token)
    {
        $s = $this->scanner($token);
        $event = $this->eventFor($s, $r->query('event_id'));
        if (!$event || !$s->canScan($event)) {
            return $this->json(['counts' => ['in' => 0, 'issued' => 0, 'remaining' => 0], 'recent' => []]);
        }

        return $this->json(['counts' => AccessControl::counts($event), 'recent' => AccessControl::recent($event)]);
    }
}
