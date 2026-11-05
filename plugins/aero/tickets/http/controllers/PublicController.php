<?php namespace Aero\Tickets\Http\Controllers;

use Aero\Tickets\Classes\AccessLink;
use Aero\Tickets\Classes\DuplicateOrderException;
use Aero\Tickets\Classes\OrderService;
use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Classes\Providers\Registry;
use Aero\Tickets\Classes\TicketDelivery;
use Aero\Tickets\Classes\TicketIssuer;
use Aero\Tickets\Classes\TicketRenderer;
use Aero\Tickets\Models\Category;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\OrderItem;
use Aero\Tickets\Models\Payment;
use Aero\Tickets\Models\PaymentSetting;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Lado público. El tenant sale SIEMPRE del host (nunca de un parámetro) y todo
 * se filtra por él: sin tenant resuelto, 404. Los eventos privados no revelan
 * su existencia sin el token correcto.
 */
class PublicController extends Controller
{
    protected function tenantId(): int
    {
        $tenant = class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost())
            : null;

        abort_unless($tenant, 404);

        return (int) $tenant->id;
    }

    /** Evento visible para este visitante, o 404. */
    protected function findEvent(string $slug, ?string $token): Event
    {
        $e = Event::forTenant($this->tenantId())->where('slug', $slug)
            ->whereIn('status', ['published', 'ongoing', 'finished', 'cancelled'])->first();

        abort_unless($e, 404);
        abort_if(in_array($e->status, ['published', 'ongoing'], true) && $e->platformBlocked(), 404);

        $ok = match ($e->visibility) {
            'public' => true,
            'link'   => $token && $e->access_token && hash_equals($e->access_token, $token),
            default  => false,
        };
        abort_unless($ok, 404);

        return $e;
    }

    public function catalog(Request $r)
    {
        $tid = $this->tenantId();
        $q = Event::forTenant($tid)->listedPublicly()->with('category')
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));

        if ($s = trim((string) $r->query('q'))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('venue_name', 'like', $like)->orWhere('zone', 'like', $like));
        }
        if ($c = $r->query('categoria')) {
            $q->whereHas('category', fn ($w) => $w->where('slug', $c));
        }
        if ($city = $r->query('ciudad')) {
            $q->where('city', $city);
        }
        if ($from = $r->query('desde')) {
            $q->whereDate('starts_at', '>=', $from);
        }
        if ($to = $r->query('hasta')) {
            $q->whereDate('starts_at', '<=', $to);
        }

        $base = Event::forTenant($tid)->listedPublicly();

        return view('aerotickets::catalog', [
            'events' => $q->orderBy('starts_at')->paginate(12)->withQueryString(),
            'categories' => Category::active()->get(),
            'cities' => (clone $base)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'f' => $r->only(['q', 'categoria', 'ciudad', 'desde', 'hasta']),
        ]);
    }

    public function show(Request $r, string $slug)
    {
        $token = (string) $r->query('t', $r->old('t', ''));
        $e = $this->findEvent($slug, $token ?: null);

        return view('aerotickets::event', [
            'e' => $e,
            'token' => $token,
            'types' => $e->ticket_types()->where('is_active', true)->orderBy('sort_order')->get()->each(fn ($t) => [$t->quote = \Aero\Tickets\Classes\Pricing::quote($t), $t->inv = \Aero\Tickets\Classes\Inventory::type($t)]),
            'left' => $e->available(),
        ]);
    }

    public function buy(Request $r, string $slug)
    {
        $token = (string) $r->input('t', '');
        $e = $this->findEvent($slug, $token ?: null);

        $data = $r->validate([
            'qty' => 'required|array', 'qty.*' => 'nullable|integer|min:0|max:50',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|min:6|max:30',
            'document' => 'nullable|string|max:30',
            'extra' => ($e->buyer_extra_required && $e->buyer_extra_label ? 'required' : 'nullable') . '|string|max:500',
            'nonce' => 'nullable|string|max:64',
        ]);

        $lines = [];
        foreach ($data['qty'] as $typeId => $qty) {
            if ((int) $qty > 0) {
                $lines[] = [(int) $typeId, (int) $qty];
            }
        }
        $back = fn (string $msg) => back()->withInput()->withErrors(['general' => $msg]);

        try {
            $order = OrderService::place($e, $lines, [
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                'document' => $data['document'] ?? null, 'extra' => $data['extra'] ?? null,
            ], 'public', ['idempotency_key' => !empty($data['nonce']) ? sha1($e->id . '|' . $data['nonce']) : null]);
        } catch (DuplicateOrderException $ex) {
            return redirect('orden/' . $ex->existing->code)->with('notice', $ex->getMessage() . ' Termínala o cancélala antes de hacer otra.');
        } catch (\ApplicationException $ex) {
            return $back($ex->getMessage());
        }

        return redirect('orden/' . $order->code);
    }

    protected function findOrder(string $code): Order
    {
        $o = Order::forTenant($this->tenantId())->where('code', strtoupper($code))->first();
        abort_unless($o, 404);

        return $o;
    }

    public function order(string $code)
    {
        $o = $this->findOrder($code);

        return view('aerotickets::order', [
            'o' => $o, 'e' => Event::withTrashed()->find($o->event_id),
            'items' => OrderItem::where('order_id', $o->id)->get(),
            'tickets' => $o->tickets()->orderBy('id')->get(),
            'methods' => $o->status === 'pending_payment' && !$o->isLapsed() ? PaymentService::availableMethods($o) : [],
            'payment' => $payment = Payment::where('order_id', $o->id)->whereNotIn('status', ['cancelled'])->orderByDesc('id')->first(),
            'present' => $payment && $payment->provider ? (Registry::byKey($payment->provider)?->present($payment) ?? []) : [],
            'setting' => PaymentSetting::forTenant($o->tenant_id),
            'rejected' => Payment::where('order_id', $o->id)->where('status', 'rejected')->orderByDesc('id')->first(),
        ]);
    }

    public function pay(Request $r, string $code)
    {
        $o = $this->findOrder($code);
        $data = $r->validate(['method' => 'required|string|max:10']);

        try {
            PaymentService::start($o, $data['method']);
        } catch (\ApplicationException $ex) {
            return back()->withErrors(['general' => $ex->getMessage()]);
        }

        return redirect('orden/' . $o->code);
    }

    public function inform(Request $r, string $code)
    {
        $o = $this->findOrder($code);
        $data = $r->validate([
            'reference' => 'nullable|string|max:255', 'sender' => 'nullable|string|max:255',
            'proof' => 'nullable|file|max:3072',
        ]);

        $payment = Payment::where('order_id', $o->id)->whereIn('status', ['pending', 'expired'])->orderByDesc('id')->first();
        if (!$payment) {
            return back()->withErrors(['general' => 'Primero elige cómo vas a pagar.']);
        }

        $proof = null;
        if ($f = $r->file('proof')) {
            $proof = ['content' => (string) file_get_contents($f->getRealPath()), 'name' => $f->getClientOriginalName()];
        }

        try {
            PaymentService::submit($payment, $data['reference'] ?? null, $data['sender'] ?? null, $proof);
        } catch (\ApplicationException $ex) {
            return back()->withErrors(['general' => $ex->getMessage()])->withInput();
        }

        return redirect('orden/' . $o->code)->with('notice', 'Recibimos tu aviso de pago. Queda pendiente de verificación: el organizador confirmará el cobro en su cuenta y tus entradas valdrán entonces.');
    }

    public function cancelOrder(string $code)
    {
        $o = $this->findOrder($code);
        if ($o->status !== 'pending_payment') {
            return back()->withErrors(['general' => 'Esta orden ya no se puede cancelar desde aquí; contacta al organizador.']);
        }

        try {
            $o->cancel('Cancelada por el comprador');
        } catch (\ApplicationException $ex) {
            return back()->withErrors(['general' => $ex->getMessage()]);
        }

        return back()->with('notice', 'Orden cancelada; el cupo fue liberado.');
    }

    protected function findTicket(string $token): Ticket
    {
        $t = Ticket::forTenant($this->tenantId())->where('public_token', $token)->first();
        abort_unless($t, 404);

        return $t;
    }

    /** Las entradas son privadas: sin caché, sin indexar y sin filtrar el enlace por referer. */
    protected function privateResponse($response)
    {
        return $response->withHeaders([
            'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function ticket(string $token)
    {
        $t = $this->findTicket($token);
        $d = TicketRenderer::details($t);

        return $this->privateResponse(response()->view('aerotickets::ticket', $d + ['qr' => $d['showQr'] ? TicketRenderer::qrSvg($t, 260) : null, 'pdfAvailable' => TicketRenderer::pdfAvailable()]));
    }

    public function ticketPdf(string $token)
    {
        $t = $this->findTicket($token);
        $pdf = TicketRenderer::pdf([$t]);
        abort_unless($pdf, 404);

        return $this->privateResponse(response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . TicketRenderer::filename($t) . '"']));
    }

    /** Todas las entradas vigentes de la orden en un solo PDF. */
    public function orderPdf(string $code)
    {
        $o = $this->findOrder($code);
        $tickets = Ticket::where('order_id', $o->id)->where('status', 'valid')->orderBy('seq')->get();
        abort_if($tickets->isEmpty(), 404);
        $pdf = TicketRenderer::pdf($tickets);
        abort_unless($pdf, 404);

        return $this->privateResponse(response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="entradas-' . strtolower($o->code) . '.pdf"']));
    }

    /** Reenvío al correo de la compra (nunca a otra dirección) con límite de uno cada 10 minutos. */
    public function resend(string $code)
    {
        $o = $this->findOrder($code);
        if ($o->status !== 'confirmed' || !$o->buyer_email) {
            return back()->withErrors(['general' => 'Esta orden no tiene entradas para enviar.']);
        }
        if (!\Cache::add('aero-tickets-resend:' . $o->id, 1, 600)) {
            return back()->withErrors(['general' => 'Ya enviamos tus entradas hace poco. Revisa tu correo (y la carpeta de spam) o espera unos minutos.']);
        }
        try {
            TicketDelivery::send($o, true);
        } catch (\Throwable $e) {
            \Log::warning('aero.tickets: reenvío fallido: ' . $e->getMessage());

            return back()->withErrors(['general' => 'No pudimos enviar el correo ahora. Descarga tus entradas aquí mismo.']);
        }

        return back()->with('notice', 'Te reenviamos las entradas a ' . $this->maskEmail($o->buyer_email) . '.');
    }

    protected function maskEmail(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($u, 0, 2) . '***@' . $d;
    }

    /* ---------- Mis entradas (sin cuenta) ---------- */

    public function myTickets()
    {
        $tid = $this->tenantId();
        $user = class_exists(\RainLab\User\Models\User::class) ? \Auth::user() : null;

        return $this->privateResponse(response()->view('aerotickets::mine', [
            'email' => $user?->email ? mb_strtolower($user->email) : null, 'groups' => $user?->email ? $this->ticketsOf(mb_strtolower($user->email), $tid) : null, 'sent' => session('sent'),
        ]));
    }

    public function myTicketsSend(Request $r)
    {
        $tid = $this->tenantId();
        $data = $r->validate(['email' => 'required|email|max:255']);
        $email = mb_strtolower($data['email']);

        // Misma respuesta exista o no el correo (no se puede averiguar quién compró). Un envío cada 2 minutos por correo.
        if (\Cache::add('aero-tickets-mine:' . sha1($tid . $email), 1, 120) && Order::forTenant($tid)->where('buyer_email', $email)->where('status', 'confirmed')->exists()) {
            $url = 'https://' . $r->getHost() . '/mis-entradas/ver?' . http_build_query(AccessLink::params($email, $tid, 60));
            \Aero\Tickets\Classes\Templates::send($email, null, 'mytickets_link', ['link_url' => $url]);
        }

        return redirect('mis-entradas')->with('sent', true);
    }

    public function myTicketsView(Request $r)
    {
        $tid = $this->tenantId();
        $email = AccessLink::verify($r->query(), $tid);
        if (!$email) {
            return redirect('mis-entradas')->withErrors(['general' => 'El enlace venció o no es válido. Pide uno nuevo.']);
        }

        return $this->privateResponse(response()->view('aerotickets::mine', ['email' => $email, 'groups' => $this->ticketsOf($email, $tid), 'sent' => false]));
    }

    protected function ticketsOf(string $email, int $tid)
    {
        $orders = Order::forTenant($tid)->where('buyer_email', $email)->whereIn('status', ['confirmed', 'pending_payment', 'verifying', 'incident'])->orderByDesc('id')->get();

        return $orders->map(fn ($o) => [
            'order' => $o, 'event' => Event::withTrashed()->find($o->event_id),
            'tickets' => Ticket::where('order_id', $o->id)->whereNotIn('status', ['expired'])->orderBy('seq')->get(),
        ]);
    }
}
