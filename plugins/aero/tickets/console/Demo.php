<?php namespace Aero\Tickets\Console;

use Aero\Tickets\Classes\AccessControl;
use Aero\Tickets\Classes\CommissionLedger;
use Aero\Tickets\Classes\ModerationService;
use Aero\Tickets\Classes\OrderService;
use Aero\Tickets\Classes\OrganizerService;
use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Classes\TicketIssuer;
use Aero\Tickets\Models\Buyer;
use Aero\Tickets\Models\Category;
use Aero\Tickets\Models\CommissionRule;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Organizer;
use Aero\Tickets\Models\PaymentSetting;
use Aero\Tickets\Models\Scanner;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Datos de DEMOSTRACIÓN de Entradas. Todo lleva el prefijo «[DEMO]» y correos @demo.invalid (no existen), sin teléfonos,
 * con el correo en modo «array» y sin asientos contables: nada sale a nadie y la contabilidad real no se toca.
 *
 *   aero:tickets-demo seed  [--tenant=25] [--second=2]
 *   aero:tickets-demo clear [--tenant=25] [--second=2]
 */
class Demo extends Command
{
    protected $signature = 'aero:tickets-demo {action : seed|clear} {--tenant=25 : espacio organizador principal} {--second=2 : segundo organizador de ejemplo (0 = ninguno)}';
    protected $description = 'Crea o borra los datos de demostración del módulo de entradas';

    public const P = '[DEMO] ';

    protected int $tenant;
    protected int $second;
    protected array $out = [];

    public function handle(): int
    {
        $this->tenant = (int) $this->option('tenant');
        $this->second = (int) $this->option('second');

        return match ($this->argument('action')) {
            'seed' => $this->seed(),
            'clear' => $this->clear(),
            default => $this->fail('Usa seed o clear.'),
        };
    }

    /* ======================================================================== */

    protected function isolate(): void
    {
        // Nada sale: ni correos reales ni asientos en la contabilidad del espacio.
        config(['mail.default' => 'array']);
        app('mail.manager')->purge();
        \Event::forget('aero.tickets.paymentApproved');
        \Event::forget('aero.tickets.paymentRefunded');
        \Event::listen('aero.tickets.paymentApproved', fn ($p, $o) => CommissionLedger::accrue($o, $p));
        \BackendAuth::login(\Backend\Models\User::findOrFail(1));
    }

    protected function cover(string $title, array $rgb): \System\Models\File
    {
        $im = imagecreatetruecolor(1000, 560);
        $bg = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        $dk = imagecolorallocate($im, (int) ($rgb[0] * .6), (int) ($rgb[1] * .6), (int) ($rgb[2] * .6));
        $wh = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, 1000, 560, $bg);
        imagefilledrectangle($im, 0, 380, 1000, 560, $dk);
        imagestring($im, 5, 40, 40, 'DEMO', $wh);
        imagestring($im, 5, 40, 400, mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), $wh);
        $path = tempnam(sys_get_temp_dir(), 'demo') . '.png';
        imagepng($im, $path);

        return (new \System\Models\File())->fromFile($path);
    }

    protected function event(int $tenant, string $name, string $cat, array $o, array $rgb = [79, 70, 229]): Event
    {
        $e = Event::create($o + [
            'tenant_id' => $tenant, 'name' => self::P . $name, 'category_id' => Category::where('name', $cat)->value('id'),
            'description' => "Evento de demostración «{$name}». Sirve para probar el flujo completo: compra, pago, entrada con QR e ingreso.",
            'organizer_name' => self::P . 'Market Eventos', 'organizer_description' => 'Organizador de ejemplo.', 'contact_name' => 'Equipo de eventos',
            'contact_email' => 'eventos@demo.invalid', 'department' => 'La Paz', 'city' => 'La Paz',
            'venue_name' => 'Centro de Convenciones Demo', 'address' => 'Av. Arce 1234', 'address_reference' => 'Frente a la plaza principal',
            'lat' => -16.5, 'lng' => -68.15, 'min_age' => null, 'requirements' => 'Presentar el QR de la entrada (celular o impreso).',
        ]);
        $e->cover = $this->cover($name, $rgb);
        $e->save();

        return $e->fresh();
    }

    protected function type(Event $e, string $name, string $kind, float $price = 0, ?int $qty = null, array $o = []): TicketType
    {
        return TicketType::create($o + ['event_id' => $e->id, 'name' => $name, 'kind' => $kind, 'price' => $price, 'quantity' => $qty, 'max_per_order' => 6, 'description' => '']);
    }

    protected function buyer(string $name): array
    {
        return ['name' => $name, 'email' => \Str::slug($name, '.') . '@demo.invalid', 'phone' => null];
    }

    protected function order(Event $e, array $lines, string $who): Order
    {
        return OrderService::place($e->fresh(), $lines, $this->buyer($who));
    }

    /** Un pequeño PNG para usar de «comprobante». */
    protected function proof(): array
    {
        return ['content' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 'name' => 'comprobante-demo.png'];
    }

    protected function amount(Order $o): float
    {
        return (float) $o->fresh()->total;
    }

    protected function pay(Order $o, string $ref, bool $proof = false, string $method = 'transfer'): \Aero\Tickets\Models\Payment
    {
        $p = PaymentService::start($o->fresh(), $method);

        return PaymentService::submit($p, $ref, null, $proof ? $this->proof() : null);
    }

    protected function approve(\Aero\Tickets\Models\Payment $p, ?string $note = null): void
    {
        PaymentService::approve($p->fresh(), (float) $p->amount, $note);
    }

    /* ======================================================================== */

    protected function seed(): int
    {
        if (Event::where('name', 'like', self::P . '%')->exists()) {
            $this->error('Ya hay datos de demostración. Ejecuta primero: aero:tickets-demo clear');

            return 1;
        }
        $this->isolate();
        $T = $this->tenant;

        // ---- Organizador y cobros ----
        $org = OrganizerService::ensure($T);
        OrganizerService::submit($org, ['legal_name' => self::P . 'Market Eventos S.R.L.', 'document' => '1020304050', 'contact_name' => 'Equipo de eventos', 'contact_email' => 'eventos@demo.invalid', 'city' => 'La Paz', 'description' => 'Conciertos, talleres y eventos privados de demostración.']);
        if (!PaymentSetting::where('tenant_id', $T)->exists()) {
            PaymentSetting::create(['tenant_id' => $T, 'transfer_enabled' => true, 'proof_policy' => 'optional', 'transfer_instructions' => self::P . "Banco Ejemplo\nCuenta 000-0000000-0\nTitular: Market Eventos S.R.L.\nGlosa: el código de tu orden", 'general_instructions' => self::P . 'Si pagas por transferencia, avisa con tu comprobante.']);
        }
        $this->line('· organizador y cobros');

        // ---- 1. Concierto público (el evento más completo) ----
        $c = $this->event($T, 'Concierto Acústico en La Paz', 'Conciertos', ['visibility' => 'public', 'capacity' => 120, 'starts_at' => now()->addDays(20)->setTime(20, 0), 'ends_at' => now()->addDays(20)->setTime(23, 30), 'buyer_extra_label' => 'Colegio o universidad (opcional)', 'min_age' => 14], [124, 58, 237]);
        $gen = $this->type($c, 'General', 'paid', 80, 80, ['description' => 'Ingreso general']);
        $vip = $this->type($c, 'VIP', 'paid', 150, 20, ['max_per_order' => 4, 'description' => 'Zona preferencial + bebida de cortesía']);
        $est = $this->type($c, 'Estudiante', 'paid', 50, 30, ['max_per_order' => 2, 'description' => 'Presentar carnet universitario']);
        $cor = $this->type($c, 'Cortesía prensa', 'courtesy', 0, 10);
        $c->publish();
        CommissionRule::create(['scope' => 'event', 'event_id' => $c->id, 'tenant_id' => $T, 'percent' => 5, 'fixed' => 1, 'payer' => 'buyer', 'note' => self::P . 'Acuerdo comercial del concierto']);

        $a = $this->order($c, [[$gen, 2]], 'María Fernández');   $this->approve($this->pay($a, 'TRF-DEMO-1001', true), 'Verificado en el banco (demo)');
        $b = $this->order($c, [[$vip, 1]], 'Carlos Quispe');     PaymentService::registerManual($b, $this->amount($b), self::P . 'Efectivo en boletería, recibo 0012');
        $d = $this->order($c, [[$est, 2]], 'Lucía Mamani');      $this->approve($this->pay($d, 'TRF-DEMO-1003'));
        $this->order($c, [[$gen, 3]], 'Jorge Rojas');                                                     // pendiente de pago (reservada)
        $e1 = $this->order($c, [[$vip, 2]], 'Ana Choque');       $this->pay($e1, 'TRF-DEMO-2002', true);   // por verificar
        $f = $this->order($c, [[$gen, 1]], 'Pedro Vargas');      $this->pay($f, 'TRF-DEMO-2002');          // referencia repetida → sospechoso
        $g = $this->order($c, [[$gen, 1]], 'Rosa Cussi');        PaymentService::reject($this->pay($g, 'FALSO-77'), 'No aparece en la cuenta del organizador');
        $h = $this->order($c, [[$vip, 1]], 'Diego Torrico');     PaymentService::reportInconsistency($this->pay($h, 'TRF-DEMO-4004', true), 'El monto del comprobante no coincide con el de la orden');
        $i = $this->order($c, [[$est, 1]], 'Elena Paz');         $pi = PaymentService::start($i, 'transfer');
        Order::where('id', $i->id)->update(['expires_at' => now()->subMinute()]);
        Order::expireLapsed();                                                                              // nadie la pagó a tiempo: se da por abandonada...
        PaymentService::submit($pi->fresh(), 'TRF-TARDE-5005');                                              // ...y el comprador paga tarde
        $j = $this->order($c, [[$gen, 1]], 'Mario Rojas');       $j->cancel(self::P . 'El comprador se arrepintió');
        TicketIssuer::issue($c->fresh(), $cor, ['name' => self::P . 'Prensa — Radio Demo', 'email' => 'prensa@demo.invalid'], 2);
        $sp = Buyer::noteOrder('spam.demo@demo.invalid', 'Comprador sospechoso', null);
        $sp->block(self::P . 'Intentos de compra con comprobantes falsos');
        $this->out['concierto'] = $c;
        $this->line('· concierto público (' . Order::where('event_id', $c->id)->count() . ' órdenes en distintos estados)');

        // ---- 2. Fiesta privada por enlace (gratuita) ----
        $k = $this->event($T, 'Cumpleaños de Sofía (15 años)', 'Cumpleaños', ['visibility' => 'link', 'capacity' => 60, 'department' => 'Cochabamba', 'city' => 'Cochabamba', 'starts_at' => now()->addDays(10)->setTime(19, 0), 'ends_at' => now()->addDays(10)->setTime(23, 59), 'requirements' => 'Vestimenta formal. Solo con el enlace de la invitación.'], [236, 72, 153]);
        $ki = $this->type($k, 'Invitado', 'free');
        $k->publish();
        foreach (['Tía Marta', 'Juan Pérez', 'Familia Gutiérrez'] as $n) { $this->order($k, [[$ki, $n === 'Familia Gutiérrez' ? 4 : 1]], $n); }
        $this->out['cumple'] = $k->fresh();
        $this->line('· cumpleaños privado por enlace (3 confirmaciones gratuitas)');

        // ---- 3. Taller por enlace con aprobación ----
        $w = $this->event($T, 'Taller de Fotografía de Producto', 'Talleres', ['visibility' => 'link', 'registration' => 'approval', 'capacity' => 25, 'department' => 'Santa Cruz', 'city' => 'Santa Cruz de la Sierra', 'starts_at' => now()->addDays(7)->setTime(9, 0), 'ends_at' => now()->addDays(7)->setTime(13, 0)], [14, 165, 233]);
        $wt = $this->type($w, 'Cupo taller', 'paid', 120, 25);
        $w->publish();
        $w1 = $this->order($w, [[$wt, 1]], 'Valeria Ortiz');   // espera aprobación
        $w2 = $this->order($w, [[$wt, 1]], 'Sergio Medina');   // espera aprobación
        $w3 = $this->order($w, [[$wt, 1]], 'Natalia Soria');   PaymentService::registerManual($w3, $this->amount($w3), self::P . 'Aprobada y cobrada en efectivo');
        $this->out['taller'] = $w->fresh();
        $this->line('· taller privado por enlace con inscripción restringida');

        // ---- 4. Seminario ya finalizado (con ingresos) y 5. Feria en curso ----
        $s = $this->event($T, 'Seminario de Emprendimiento', 'Seminarios', ['visibility' => 'public', 'capacity' => 40, 'starts_at' => now()->addDay()->setTime(9, 0), 'ends_at' => now()->addDay()->setTime(17, 0)], [5, 150, 105]);
        $st = $this->type($s, 'Gratuita', 'free');
        $s->publish();
        $fr = $this->event($T, 'Feria Universitaria (en curso)', 'Eventos universitarios', ['visibility' => 'public', 'capacity' => 200, 'department' => 'Cochabamba', 'city' => 'Cochabamba', 'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(22, 0)], [245, 158, 11]);
        $ft = $this->type($fr, 'Libre', 'free');
        $fr->publish();

        $door = new Scanner(['name' => self::P . 'Puerta principal']); $door->tenant_id = $T; $door->save(); $door->events()->sync([$c->id, $s->id, $fr->id]);
        $side = new Scanner(['name' => self::P . 'Staff lateral']);     $side->tenant_id = $T; $side->save(); $side->events()->sync([$fr->id]);
        $scan = fn (string $code, Event $ev, Scanner $sc, string $m = 'qr') => AccessControl::validate($code, $ev->fresh(), AccessControl::scannerOperator($sc), $m, $sc);

        $stk = []; foreach (['Camila Rojas', 'Luis Alvarado', 'Fabiola Cruz', 'Iván Soliz', 'Gabriela Núñez', 'Hugo Salas'] as $n) { $stk[] = $this->order($s, [[$st, 1]], $n)->tickets()->first(); }
        foreach (array_slice($stk, 0, 4) as $tk) { $scan($tk->qr_payload, $s, $door); }
        $scan($stk[0]->qr_payload, $s, $door);                                              // doble lectura → denegada
        $scan($stk[1]->code, $s, $door, 'code');                                            // ya utilizada
        $scan('AT1-ZZZZZZZZZZ-AAAAAAAA', $s, $door);                                        // firma falsa
        // el seminario pasa a «finalizado» (ayer) y la feria queda «en curso» (hoy)
        DB::table('aero_tickets_events')->where('id', $s->id)->update(['starts_at' => now()->subDays(3)->setTime(9, 0), 'ends_at' => now()->subDays(3)->setTime(17, 0), 'status' => 'finished']);
        $ftk = []; foreach (['Valentina Gómez', 'Ricardo Peña', 'Sandra Luna', 'Omar Vega', 'Karen Ibáñez', 'Nelson Cori'] as $n) { $ftk[] = $this->order($fr, [[$ft, 1]], $n)->tickets()->first(); }
        foreach (array_slice($ftk, 0, 3) as $tk) { $scan($tk->qr_payload, $fr, $side); }
        DB::table('aero_tickets_events')->where('id', $fr->id)->update(['starts_at' => now()->subHours(2), 'ends_at' => now()->addHours(6), 'status' => 'ongoing']);
        $this->out['feria'] = [$fr, array_slice($ftk, 3), $door];
        $this->line('· seminario finalizado (ingresos y rechazos) y feria en curso (3 ingresaron, 3 por ingresar)');

        // ---- 6. Cancelado por la plataforma ----
        $t = $this->event($T, 'Noche de Teatro', 'Teatro y espectáculos', ['visibility' => 'public', 'capacity' => 80, 'starts_at' => now()->addDays(15)->setTime(20, 0)], [190, 18, 60]);
        $tt = $this->type($t, 'Platea', 'paid', 60, 80);
        $t->publish();
        $t1 = $this->order($t, [[$tt, 2]], 'Familia Ugarte');   $this->approve($this->pay($t1, 'TRF-DEMO-9001'));
        $t2 = $this->order($t, [[$tt, 1]], 'Pablo Mendoza');    PaymentService::registerManual($t2, $this->amount($t2), self::P . 'Efectivo');
        $this->order($t, [[$tt, 1]], 'Silvia Flores');
        ModerationService::cancel($t->fresh(), self::P . 'Incumplimiento de las condiciones del evento');
        $this->line('· evento cancelado por la plataforma (pagos a devolver)');

        // ---- 7. Suspendido y 8. Borrador ----
        $x = $this->event($T, 'Fiesta Electrónica', 'Fiestas privadas', ['visibility' => 'public', 'capacity' => 300, 'starts_at' => now()->addDays(25)->setTime(22, 0)], [17, 24, 39]);
        $this->type($x, 'General', 'paid', 100, 300);
        $x->publish();
        ModerationService::suspend($x->fresh(), self::P . 'Reportes de venta de entradas falsas');
        $b = $this->event($T, 'Borrador sin publicar', 'Otro', ['visibility' => 'public', 'capacity' => 50, 'starts_at' => now()->addDays(40)->setTime(18, 0)], [107, 114, 128]);
        $this->type($b, 'General', 'free');
        $this->line('· evento suspendido y un borrador');

        // ---- 9. Segundo organizador (solo si se pidió) ----
        if ($this->second > 0 && $this->second !== $T) {
            $S = $this->second;
            $o2 = Organizer::where('tenant_id', $S)->first();
            if ($o2) {
                $this->warn("El espacio {$S} ya tiene perfil de organizador: no se toca. Usa --second=0 para omitirlo.");
            } else {
                DB::table('aero_tickets_organizers')->insert([
                    'tenant_id' => $S, 'status' => 'pending', 'legal_name' => self::P . 'Gastro Eventos Bolivia', 'document' => '9988776655', 'contact_name' => 'Rita Montaño',
                    'contact_email' => 'rita@demo.invalid', 'city' => 'Sucre', 'description' => 'Ferias gastronómicas y degustaciones.', 'requested_at' => now()->subHours(5), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $f2 = $this->event($S, 'Feria Gastronómica', 'Festivales', ['visibility' => 'public', 'capacity' => 150, 'department' => 'Chuquisaca', 'city' => 'Sucre', 'starts_at' => now()->addDays(30)->setTime(11, 0), 'ends_at' => now()->addDays(30)->setTime(20, 0)], [234, 88, 12]);
                $ft2 = $this->type($f2, 'Entrada', 'paid', 40, 150);
                $f2->publish();
                CommissionRule::create(['scope' => 'tenant', 'tenant_id' => $S, 'percent' => 6, 'payer' => 'organizer', 'note' => self::P . 'Acuerdo piloto con el organizador']);
                $o = $this->order($f2, [[$ft2, 2]], 'Cliente Sucre'); PaymentService::registerManual($o, $this->amount($o), self::P . 'Efectivo');
                DB::table('aero_tickets_events')->where('id', $f2->id)->update(['moderation' => 'pending']);     // en espera de moderación
                $this->line("· segundo organizador (espacio {$S}): solicitud pendiente + evento en revisión + regla de comisión");
            }
        }

        $this->summary();

        return 0;
    }

    protected function summary(): void
    {
        $T = $this->tenant;
        $host = \Aero\Sites\Models\Tenant::find($T)?->primary_domain ?: 'master.market.com.bo';
        $c = $this->out['concierto'];
        $k = $this->out['cumple'];
        [$fr, $porIngresar, $door] = $this->out['feria'];
        $w = $this->out['taller'];
        $this->newLine();
        $this->info('Datos de demostración creados en el espacio ' . $T . ' (' . $host . ').');
        $this->table(['Qué', 'Cuántos'], [
            ['Eventos [DEMO]', Event::where('name', 'like', self::P . '%')->count()],
            ['Órdenes', Order::whereIn('event_id', Event::where('name', 'like', self::P . '%')->pluck('id'))->count()],
            ['Entradas', Ticket::whereIn('event_id', Event::where('name', 'like', self::P . '%')->pluck('id'))->count()],
            ['Pagos', \Aero\Tickets\Models\Payment::whereIn('event_id', Event::where('name', 'like', self::P . '%')->pluck('id'))->count()],
            ['Comisiones (libro)', \Aero\Tickets\Models\CommissionEntry::whereIn('event_id', Event::where('name', 'like', self::P . '%')->pluck('id'))->count()],
        ]);
        $this->line("Catálogo público ........ https://{$host}/eventos");
        $this->line('Concierto ............... ' . $c->share_url);
        $this->line('Taller (solo enlace) .... ' . $w->share_url);
        $this->line('Cumpleaños (solo enlace)  ' . $k->share_url);
        $this->line('Escáner móvil ........... https://' . $host . '/escaner/' . $door->plainToken . '   (secreto: solo se muestra ahora; se regenera en Escáneres)');
        $this->line('QR válidos en la feria (para probar el escáner):');
        foreach ($porIngresar as $tk) {
            $this->line('   ' . $tk->holder_name . ' → ' . $tk->qr_payload);
        }
    }

    /* ======================================================================== */

    protected function clear(): int
    {
        $this->isolate();
        $ids = Event::withTrashed()->where('name', 'like', self::P . '%')->pluck('id')->all();
        $orders = Order::whereIn('event_id', $ids)->pluck('id')->all();
        $payments = \Aero\Tickets\Models\Payment::whereIn('event_id', $ids)->pluck('id')->all();
        $n = [];
        DB::transaction(function () use ($ids, $orders, $payments, &$n) {
            $n['escaneos'] = DB::table('aero_tickets_scans')->whereIn('event_id', $ids)->delete();
            $n['comprobantes'] = DB::table('aero_tickets_payment_proofs')->whereIn('payment_id', $payments)->delete();
            $n['historial de pagos'] = DB::table('aero_tickets_payment_events')->where(fn ($q) => $q->whereIn('payment_id', $payments)->orWhereIn('order_id', $orders))->delete();
            $n['pagos'] = DB::table('aero_tickets_payments')->whereIn('id', $payments)->delete();
            $n['comisiones'] = DB::table('aero_tickets_commission_entries')->whereIn('order_id', $orders)->delete();
            $n['entradas'] = DB::table('aero_tickets_tickets')->whereIn('event_id', $ids)->delete();
            $n['líneas de orden'] = DB::table('aero_tickets_order_items')->whereIn('order_id', $orders)->delete();
            $n['órdenes'] = DB::table('aero_tickets_orders')->whereIn('id', $orders)->delete();
            $n['tipos de entrada'] = DB::table('aero_tickets_ticket_types')->whereIn('event_id', $ids)->delete();
            $sc = DB::table('aero_tickets_scanners')->where('name', 'like', self::P . '%')->pluck('id')->all();
            DB::table('aero_tickets_scanner_events')->whereIn('scanner_id', $sc)->delete();
            $n['escáneres'] = DB::table('aero_tickets_scanners')->whereIn('id', $sc)->delete();
            DB::table('aero_tickets_commission_rules')->where('note', 'like', self::P . '%')->delete();
            $n['compradores'] = DB::table('aero_tickets_buyers')->where('email', 'like', '%@demo.invalid')->delete();
            // imágenes de portada
            foreach (\System\Models\File::where('attachment_type', Event::class)->whereIn('attachment_id', array_map('strval', $ids))->get() as $f) { $f->delete(); }
            $n['eventos'] = DB::table('aero_tickets_events')->whereIn('id', $ids)->delete();
            // organizadores / cobros de demostración
            DB::table('aero_tickets_organizers')->where('legal_name', 'like', self::P . 'Gastro%')->delete();
            DB::table('aero_tickets_organizers')->where('legal_name', 'like', self::P . '%')->update(['legal_name' => null, 'document' => null, 'contact_name' => null, 'contact_email' => null, 'contact_phone' => null, 'city' => null, 'description' => null]);
            DB::table('aero_tickets_payment_settings')->where('transfer_instructions', 'like', self::P . '%')->delete();
        });
        $this->info('Datos de demostración borrados:');
        $this->table(['Qué', 'Filas'], collect($n)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->line('La auditoría es permanente y conserva el rastro de lo que se creó (así debe ser).');

        return 0;
    }
}
