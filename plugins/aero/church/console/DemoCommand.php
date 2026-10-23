<?php namespace Aero\Church\Console;

use Aero\Church\Classes\Audit;
use Aero\Church\Classes\CareService;
use Aero\Church\Classes\ContributionService;
use Aero\Church\Classes\EventService;
use Aero\Church\Classes\Messenger;
use Aero\Church\Classes\Notifier;
use Aero\Church\Models\Announcement;
use Aero\Church\Models\Attendance;
use Aero\Church\Models\Branch;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Contribution;
use Aero\Church\Models\EventRegistration;
use Aero\Church\Models\Family;
use Aero\Church\Models\Fund;
use Aero\Church\Models\Group;
use Aero\Church\Models\GroupMember;
use Aero\Church\Models\Meeting;
use Aero\Church\Models\Member;
use Aero\Church\Models\Post;
use Aero\Church\Models\Prayer;
use Aero\Church\Models\PrayerNote;
use Aero\Church\Models\Resource;
use Aero\Church\Models\Sermon;
use Aero\Church\Models\Series;
use Aero\Church\Models\Visit;
use Aero\Church\Models\VisitContact;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * church:demo {tenant} [--fresh] [--public] — datos de demostración de UNA iglesia para probar cada pantalla:
 * miembros, familias, grupos con asistencia, cultos pasados y futuros, aportes, visitantes, oración, contenido.
 *
 * SEGURO: sin teléfonos (NULL), correos @example.invalid, ningún WhatsApp, aviso ni asiento contable (los
 * oyentes de aero.church.* se anulan durante la carga) y los formularios/sitio/portal públicos APAGADOS salvo
 * --public. Lo creado se anota en storage/app/church-demo-{tenant}.json: --fresh borra SOLO esos ids.
 */
class DemoCommand extends Command
{
    protected $signature = 'church:demo {tenant : Id de la iglesia (tenant)} {--fresh : Borra lo sembrado antes y vuelve a sembrar} {--clean : Solo borra lo sembrado} {--public : Activa sitio público, portal y formularios}';
    protected $description = 'Carga (o limpia) datos de demostración de una iglesia, sin contactar a nadie.';

    protected int $tid;
    protected array $made = [];
    protected Carbon $today;

    public function handle(): int
    {
        $this->tid = (int) $this->argument('tenant');
        if (!class_exists(\Aero\Sites\Models\Tenant::class) || !\Aero\Sites\Models\Tenant::find($this->tid)) {
            $this->error('No existe el tenant ' . $this->tid);

            return self::FAILURE;
        }

        if ($this->option('fresh') || $this->option('clean')) {
            $this->clean();
            if ($this->option('clean')) {
                return self::SUCCESS;
            }
        } elseif (is_file($this->manifest())) {
            $this->error('Ya hay datos de demostración en este tenant. Usa --fresh para recargarlos o --clean para quitarlos.');

            return self::FAILURE;
        }

        // Ningún aviso, mensaje, asiento ni historial durante la carga.
        Messenger::$muted = true;
        Notifier::$muted = true;
        Audit::$muted = true;
        foreach (['contributionConfirmed', 'contributionCancelled', 'visitRegistered', 'prayerRequested', 'eventChanged', 'eventRegistered', 'eventWaitlisted', 'eventRegistrationCancelled', 'eventWaitlistPromoted', 'eventCheckedIn', 'visitIntegrated', 'announcementSent'] as $e) {
            Event::forget('aero.church.' . $e);
        }

        mt_srand(25025);
        $this->today = now()->startOfDay();

        DB::transaction(function () {
            $this->branchesAndFunds();
            $this->people();
            $this->groups();
            $this->events();
            $this->giving();
            $this->visits();
            $this->prayers();
            $this->announcements();
            $this->content();
            $this->settings();
        });

        file_put_contents($this->manifest(), json_encode($this->made));
        $this->summary();

        return self::SUCCESS;
    }

    // ───────────────────────── utilidades ─────────────────────────

    protected function manifest(): string
    {
        return storage_path('app/church-demo-' . $this->tid . '.json');
    }

    /** Crea un registro con campos libres (fechas pasadas incluidas) y lo anota para poder borrarlo luego. */
    protected function make(string $class, array $attrs)
    {
        $m = (new $class)->forceFill(['tenant_id' => $this->tid] + $attrs);
        $m->save();
        $this->made[$m->getTable()][] = $m->getKey();

        return $m;
    }

    protected function backdate(string $table, int $id, array $cols): void
    {
        DB::table($table)->where('id', $id)->update($cols);
    }

    protected function pick(array $a)
    {
        return $a[mt_rand(0, count($a) - 1)];
    }

    protected function chance(int $pct): bool
    {
        return mt_rand(1, 100) <= $pct;
    }

    protected function sundayAgo(int $weeks): Carbon
    {
        return $this->today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1)->subDay();   // domingo anterior
    }

    protected array $m = [];       // miembros por clave
    protected array $adults = [];
    protected array $grp = [];
    protected array $fund = [];
    protected array $branch = [];

    // ───────────────────────── sedes y conceptos ─────────────────────────

    protected function branchesAndFunds(): void
    {
        $this->branch['c'] = $this->make(Branch::class, ['name' => 'Sede Central', 'city' => 'La Paz', 'address' => 'Av. Camacho 1234', 'is_main' => true, 'is_active' => true]);
        $this->branch['s'] = $this->make(Branch::class, ['name' => 'Sede Zona Sur', 'city' => 'La Paz', 'address' => 'Calle 21 de Calacoto 560', 'is_main' => false, 'is_active' => true]);

        foreach (['Diezmo' => 'tithe', 'Ofrenda' => 'offering', 'Donación' => 'donation'] as $name => $kind) {
            $existing = Fund::where('tenant_id', $this->tid)->where('name', $name)->first();
            $this->fund[$kind] = $existing ?: $this->make(Fund::class, ['name' => $name, 'kind' => $kind, 'is_active' => true]);
        }
        $this->fund['missions'] = $this->make(Fund::class, ['name' => 'Misiones', 'kind' => 'offering', 'is_active' => true]);
        $this->fund['building'] = $this->make(Fund::class, ['name' => 'Campaña templo nuevo', 'kind' => 'campaign', 'goal_amount' => 80000, 'starts_on' => $this->today->copy()->subMonths(4)->toDateString(), 'ends_on' => $this->today->copy()->addMonths(8)->toDateString(), 'is_active' => true]);
    }

    // ───────────────────────── personas ─────────────────────────

    protected function people(): void
    {
        $families = [
            ['Mamani Quispe', 3], ['Condori Flores', 2], ['Choque Gutiérrez', 1], ['Vargas Rojas', 3], ['Quispe Mendoza', 0],
            ['Torrez Salazar', 2], ['Huanca Pérez', 1], ['Rodríguez Cruz', 2], ['Villca Apaza', 0], ['Sánchez Arce', 2],
        ];
        $men = ['Juan', 'Carlos', 'Luis', 'Pedro', 'José', 'Marcos', 'Daniel', 'Samuel', 'David', 'Mateo', 'Ricardo', 'Fernando'];
        $women = ['María', 'Ana', 'Rosa', 'Lucía', 'Elena', 'Carmen', 'Sofía', 'Patricia', 'Julia', 'Gabriela', 'Rebeca', 'Esther'];
        $kids = ['Valentina', 'Mateo', 'Camila', 'Emanuel', 'Abigail', 'Josué', 'Noemí', 'Isaac', 'Lía', 'Thiago', 'Sara', 'Benjamín', 'Ruth', 'Gael'];
        $ki = 0;
        $mi = 0;
        $wi = 0;
        $n = 0;

        foreach ($families as $i => [$sur, $children]) {
            [$s1, $s2] = explode(' ', $sur);
            $fam = $this->make(Family::class, ['name' => "Familia $s1 $s2", 'address' => 'Zona ' . $this->pick(['Villa Fátima', 'Sopocachi', 'Calacoto', 'Obrajes', 'El Alto Ciudad Satélite']) . ' #' . mt_rand(10, 900)]);
            $branch = $this->chance(70) ? $this->branch['c'] : $this->branch['s'];

            $head = $this->person($men[$mi++ % count($men)], $sur, 'head', $fam, $branch, ['gender' => 'm', 'civil_status' => 'married', 'age' => mt_rand(30, 55)]);
            $spouse = $this->person($women[$wi++ % count($women)], $sur, 'spouse', $fam, $branch, ['gender' => 'f', 'civil_status' => 'married', 'age' => mt_rand(28, 53), 'marriage' => $this->today->copy()->subYears(mt_rand(3, 25))]);
            $this->adults[] = $head;
            $this->adults[] = $spouse;
            $this->m["h$i"] = $head;
            $this->m["w$i"] = $spouse;

            for ($c = 0; $c < $children; $c++) {
                $age = mt_rand(2, 17);
                $this->person($kids[$ki++ % count($kids)], $sur, 'child', $fam, $branch, ['gender' => $this->chance(50) ? 'm' : 'f', 'civil_status' => 'single', 'age' => $age, 'guardian' => $head, 'status' => 'active']);
            }
            $n++;
        }

        // Solteros, jóvenes y adultos mayores
        $singles = [
            ['Andrea', 'Lima Torrico', 'f', 19], ['Diego', 'Paco Ticona', 'm', 22], ['Karen', 'Poma Limachi', 'f', 24], ['Bryan', 'Chura Colque', 'm', 20],
            ['Doña Eulogia', 'Ticona Mamani', 'f', 71], ['Don Facundo', 'Apaza Yujra', 'm', 68], ['Silvia', 'Mercado Ortiz', 'f', 38], ['Rolando', 'Gómez Vera', 'm', 45],
            ['Nayra', 'Flores Quenta', 'f', 26], ['Alan', 'Cussi Mamani', 'm', 17],
        ];
        foreach ($singles as $k => [$fn, $ln, $g, $age]) {
            $p = $this->person($fn, $ln, null, null, $this->chance(60) ? $this->branch['c'] : $this->branch['s'], ['gender' => $g, 'civil_status' => $age > 60 ? 'widowed' : 'single', 'age' => $age]);
            if ($age >= 18) {
                $this->adults[] = $p;
            }
            $this->m['s' . $k] = $p;
        }

        // Nuevos asistentes, visitantes e inactivos
        foreach ([['Gonzalo', 'Rivero Sandoval', 'new', 31], ['Mariela', 'Cárdenas Ruiz', 'new', 27], ['Pablo', 'Aguilar Lara', 'visitor', 35], ['Teresa', 'Nina Choque', 'inactive', 49], ['Hugo', 'Zapata Díaz', 'inactive', 57]] as $k => [$fn, $ln, $st, $age]) {
            $this->m['x' . $k] = $this->person($fn, $ln, null, null, $this->branch['c'], ['gender' => in_array($fn, ['Mariela', 'Teresa']) ? 'f' : 'm', 'civil_status' => 'single', 'age' => $age, 'status' => $st, 'recent' => $st !== 'inactive']);
        }

        // Cumpleaños dentro de la próxima semana (para el resumen)
        foreach ([[$this->adults[0], 1], [$this->adults[3], 3], [$this->adults[7], 5], [$this->m['s2'], 6]] as [$member, $off]) {
            $age = max(19, (int) $member->age);
            DB::table('aero_church_members')->where('id', $member->id)->update(['birth_date' => $this->today->copy()->addDays($off)->subYears($age)->toDateString()]);
        }
    }

    protected function person(string $first, string $last, ?string $role, $fam, $branch, array $o): Member
    {
        $status = $o['status'] ?? ($this->chance(88) ? 'active' : 'new');
        $age = $o['age'];
        $created = !empty($o['recent']) ? $this->today->copy()->subDays(mt_rand(3, 25)) : $this->today->copy()->subMonths(mt_rand(0, 11))->subDays(mt_rand(0, 27));
        $adult = $age >= 18;
        $isNew = in_array($status, ['new', 'visitor']);

        $m = $this->make(Member::class, [
            'branch_id' => $branch->id, 'family_id' => $fam?->id, 'family_role' => $role, 'guardian_id' => isset($o['guardian']) ? $o['guardian']->id : null,
            'first_name' => $first, 'last_name' => $last, 'gender' => $o['gender'], 'civil_status' => $o['civil_status'],
            'birth_date' => $this->today->copy()->subYears($age)->subDays(mt_rand(5, 330))->toDateString(),
            'marriage_date' => isset($o['marriage']) ? $o['marriage']->toDateString() : null,
            'email' => $adult && $this->chance(55) ? Str::slug($first . '.' . explode(' ', $last)[0], '.') . mt_rand(1, 99) . '@example.invalid' : null,
            'phone' => null,    // SIN teléfonos: nada puede escribirle a una persona real
            'status' => $status,
            'joined_at' => $isNew ? null : $created->copy()->subMonths(mt_rand(0, 40))->toDateString(),
            'baptized' => !$isNew && $adult && $this->chance(75), 'baptism_date' => null,
            'consent_messages' => $adult && $this->chance(70), 'consent_data' => $adult || isset($o['guardian']),
            'created_at' => $created, 'updated_at' => $created,
        ]);

        return $m;
    }

    // ───────────────────────── grupos y reuniones ─────────────────────────

    protected function groups(): void
    {
        $defs = [
            ['alab', 'Alabanza y adoración', 'ministry', 'sat', '18:00', 'Templo principal', 'h0', 6],
            ['jov', 'Jóvenes', 'youth', 'fri', '19:30', 'Salón de jóvenes', 's1', 7],
            ['nin', 'Escuela dominical (niños)', 'children', 'sun', '09:00', 'Aulas del segundo piso', 'w3', 5],
            ['cn', 'Célula Norte', 'cell', 'wed', '19:00', 'Casa de la familia Mamani', 'h0', 6],
            ['cs', 'Célula Sur', 'cell', 'thu', '19:30', 'Casa de la familia Torrez', 'h5', 6],
            ['int', 'Intercesión', 'ministry', 'tue', '06:00', 'Sala de oración', 'w2', 4],
        ];
        $pool = $this->adults;

        foreach ($defs as [$key, $name, $type, $day, $time, $where, $leaderKey, $size]) {
            $leader = $this->m[$leaderKey] ?? $pool[0];
            $g = $this->make(Group::class, [
                'branch_id' => $this->branch['c']->id, 'leader_id' => $leader->id, 'name' => $name, 'type' => $type, 'meeting_day' => $day,
                'meeting_time' => $time, 'location' => $where, 'is_active' => true, 'description' => 'Grupo de demostración.',
            ]);
            $this->grp[$key] = $g;

            $ids = [$leader->id => 'leader'];
            $cand = $pool;
            shuffle($cand);
            foreach ($cand as $c) {
                if (count($ids) >= $size) {
                    break;
                }
                $ids[$c->id] = count($ids) === 1 ? 'assistant' : 'member';
            }
            if ($type === 'youth') {   // los jóvenes sí incluyen adolescentes y adultos jóvenes
                foreach (['s0', 's1', 's2', 's3', 's8', 's9'] as $k) {
                    $ids[$this->m[$k]->id] = $ids[$this->m[$k]->id] ?? 'member';
                }
            }
            foreach ($ids as $mid => $role) {
                $this->make(GroupMember::class, ['group_id' => $g->id, 'member_id' => $mid, 'role' => $role, 'joined_at' => $this->today->copy()->subMonths(mt_rand(1, 14))->toDateString()]);
            }
        }

        // Reuniones semanales de las últimas 8 semanas con asistencia
        foreach (['jov', 'alab', 'cn', 'cs'] as $key) {
            $g = $this->grp[$key];
            $members = GroupMember::where('group_id', $g->id)->pluck('member_id')->all();
            for ($w = 8; $w >= 1; $w--) {
                $date = $this->today->copy()->subWeeks($w)->toDateString();
                $mt = $this->make(Meeting::class, ['group_id' => $g->id, 'meeting_date' => $date, 'start_time' => $g->meeting_time, 'location' => $g->location, 'title' => $w === 1 ? 'Última reunión' : null]);
                foreach ($members as $mid) {
                    $this->make(Attendance::class, ['meeting_id' => $mt->id, 'member_id' => $mid, 'present' => $this->chance(78)]);
                }
            }
        }
    }

    // ───────────────────────── eventos ─────────────────────────

    protected function events(): void
    {
        $everyone = array_merge($this->adults, array_values(array_filter($this->m, fn ($x) => $x->status === 'active' && !$x->is_minor)));
        $everyone = array_values(array_unique(array_map(fn ($x) => $x->id, $everyone)));

        // Cultos pasados: inscritos y llegadas
        for ($w = 8; $w >= 1; $w--) {
            $d = $this->sundayAgo($w)->setTime(10, 0);
            $e = $this->make(ChurchEvent::class, ['branch_id' => $this->branch['c']->id, 'title' => 'Culto dominical', 'type' => 'service', 'starts_at' => $d, 'ends_at' => $d->copy()->addHours(2), 'location' => 'Templo principal', 'capacity' => 150, 'registration_open' => false, 'status' => 'scheduled', 'is_public' => true, 'description' => 'Culto de adoración y enseñanza.']);
            $take = array_slice($everyone, 0, mt_rand(18, min(32, count($everyone))));
            foreach ($take as $mid) {
                $came = $this->chance(85);
                $this->make(EventRegistration::class, ['event_id' => $e->id, 'member_id' => $mid, 'status' => 'registered', 'token' => Str::lower(Str::random(20)), 'checked_in_at' => $came ? $d->copy()->addMinutes(mt_rand(-10, 25)) : null]);
            }
            shuffle($everyone);
        }

        // Próximos (con el servicio, que aplica cupos y lista de espera)
        $svc = app(EventService::class);

        $culto = $this->make(ChurchEvent::class, ['branch_id' => $this->branch['c']->id, 'title' => 'Culto dominical', 'type' => 'service', 'starts_at' => $this->sundayAgo(0)->setTime(10, 0), 'ends_at' => $this->sundayAgo(0)->setTime(12, 0), 'location' => 'Templo principal', 'capacity' => 150, 'reminder_hours' => 24, 'is_public' => true, 'description' => 'Te esperamos este domingo.']);
        foreach (array_slice($everyone, 0, 12) as $mid) {
            $svc->register($culto, Member::find($mid));
        }

        $retiro = $this->make(ChurchEvent::class, ['branch_id' => $this->branch['c']->id, 'group_id' => $this->grp['jov']->id, 'responsible_id' => $this->m['s1']->id, 'title' => 'Retiro de jóvenes', 'type' => 'retreat', 'starts_at' => $this->today->copy()->addDays(21)->setTime(8, 0), 'ends_at' => $this->today->copy()->addDays(22)->setTime(18, 0), 'location' => 'Casa de retiros, Coroico', 'capacity' => 12, 'reminder_hours' => 48, 'is_public' => true, 'description' => 'Dos días de comunión, enseñanza y descanso. Cupo limitado.']);
        $youth = array_values(array_unique(array_merge($everyone, array_map(fn ($k) => $this->m[$k]->id, ['s0', 's1', 's2', 's3', 's8']))));
        foreach (array_slice($youth, 0, 15) as $mid) {      // 12 con cupo + 3 en lista de espera
            $svc->register($retiro, Member::find($mid));
        }
        $svc->register($retiro, null, 'Invitado de prueba', null);

        $conf = $this->make(ChurchEvent::class, ['branch_id' => $this->branch['s']->id, 'title' => 'Conferencia de familia', 'type' => 'conference', 'starts_at' => $this->today->copy()->addDays(10)->setTime(18, 30), 'ends_at' => $this->today->copy()->addDays(10)->setTime(21, 0), 'location' => 'Sede Zona Sur', 'capacity' => 100, 'is_public' => false, 'description' => 'Solo para miembros.']);
        foreach (array_slice($everyone, 0, 8) as $mid) {
            $svc->register($conf, Member::find($mid));
        }

        $cancel = $this->make(ChurchEvent::class, ['title' => 'Vigilia de oración', 'type' => 'special', 'starts_at' => $this->today->copy()->addDays(5)->setTime(21, 0), 'location' => 'Sala de oración', 'status' => 'cancelled', 'is_public' => false, 'description' => 'Cancelada por refacción del templo.']);

        // Las inscripciones hechas por el servicio (cupos, lista de espera) también se anotan para poder borrarlas.
        $known = $this->made['aero_church_event_registrations'] ?? [];
        $rest = EventRegistration::whereIn('event_id', $this->made['aero_church_events'] ?? [])->pluck('id')->all();
        $this->made['aero_church_event_registrations'] = array_values(array_unique(array_merge($known, $rest)));
    }

    // ───────────────────────── aportes ─────────────────────────

    protected function giving(): void
    {
        $svc = app(ContributionService::class);
        $payers = array_slice($this->adults, 0, 14);

        for ($w = 12; $w >= 1; $w--) {
            $date = $this->sundayAgo($w);
            foreach ($payers as $i => $p) {
                if (!$this->chance(55)) {
                    continue;
                }
                $method = $this->chance(70) ? 'cash' : 'transfer';
                $this->contribution('tithe', $p, $date, $this->pick([100, 150, 200, 250, 300, 400, 500, 800]), $method, $method === 'transfer' ? 'TRF-' . mt_rand(100000, 999999) : null);
            }
            $this->contribution('offering', null, $date, mt_rand(8, 25) * 10, 'cash', null, true);              // ofrenda anónima del culto
            if ($this->chance(60)) {
                $this->contribution('missions', $this->pick($payers), $date, $this->pick([50, 100, 150]), 'cash');
            }
            if ($w <= 8 && $this->chance(55)) {
                $this->contribution('building', $this->pick($payers), $date, $this->pick([500, 1000, 1500, 2000]), 'transfer', 'TRF-' . mt_rand(100000, 999999));
            }
        }

        $this->contribution('donation', null, $this->today->copy()->subDays(9), 300, 'cash', null, false, 'USD', 'Visitante extranjero');   // en dólares, aportante externo
        $this->contribution('tithe', $payers[1], $this->today->copy()->subDays(1), 250, 'qr');                                                 // QR pendiente
        $this->contribution('offering', $payers[2], $this->today->copy()->subDays(2), 120, 'qr');
        $this->contribution('building', $payers[3], $this->today, 3000, 'qr');

        $cancelled = $this->contribution('tithe', $payers[4], $this->today->copy()->subDays(10), 999, 'cash');
        $cancelled->markCancelled();

        // Comprobantes de algunos aportes personales
        foreach (Contribution::whereIn('id', $this->made['aero_church_contributions'] ?? [])->where('status', 'confirmed')->where('is_anonymous', false)->whereNotNull('member_id')->limit(6)->get() as $c) {
            $svc->receiptNumber($c);
        }
    }

    protected function contribution(string $fundKey, ?Member $member, Carbon $date, $amount, string $method, ?string $ref = null, bool $anon = false, string $cur = 'BOB', ?string $donor = null): Contribution
    {
        $c = $this->make(Contribution::class, [
            'fund_id' => $this->fund[$fundKey]->id, 'branch_id' => $this->branch['c']->id, 'member_id' => $member?->id, 'donor_name' => $member ? null : $donor,
            'is_anonymous' => $anon, 'amount' => $amount, 'currency' => $cur, 'date' => $date->toDateString(), 'method' => $method, 'reference' => $ref,
        ]);
        $this->backdate('aero_church_contributions', $c->id, ['created_at' => $date, 'updated_at' => $date, 'confirmed_at' => $method === 'qr' ? null : $date]);

        return $c->fresh();
    }

    // ───────────────────────── visitantes ─────────────────────────

    protected function visits(): void
    {
        $rows = [
            ['Gonzalo Rivero', 'integrated', 40, 'qr'], ['Mariela Cárdenas', 'integrated', 28, 'qr'], ['Pablo Aguilar', 'invited', 14, 'qr'],
            ['Lidia Crespo', 'contacted', 9, 'qr'], ['Marco Antelo', 'contacted', 8, 'manual'], ['Jimena Soliz', 'new', 3, 'qr'],
            ['Franz Heredia', 'new', 2, 'qr'], ['Olga Paredes', 'new', 1, 'qr'], ['Rubén Calle', 'closed', 60, 'manual'],
            ['Estela Rojas', 'invited', 18, 'qr'], ['Iván Montaño', 'contacted', 12, 'qr'], ['Daysi Álvarez', 'new', 0, 'qr'],
        ];
        $resp = [$this->m['h0'], $this->m['w1'], $this->m['s6']];
        $channels = ['call', 'whatsapp', 'visit'];

        foreach ($rows as $i => [$name, $status, $ago, $src]) {
            $d = $this->today->copy()->subDays($ago);
            $v = $this->make(Visit::class, [
                'branch_id' => $this->branch['c']->id, 'name' => $name, 'email' => Str::slug($name, '.') . '@example.invalid', 'phone' => null,
                'how_heard' => $this->pick(['Invitado por un amigo', 'Vi la iglesia en redes sociales', 'Pasé por el templo', 'Un familiar me trajo']),
                'interest' => $this->pick(['Quiero conocer los grupos de jóvenes', 'Busco una célula cerca de mi casa', 'Me interesa el ministerio de niños', 'Quiero saber de los bautismos']),
                'consent_data' => true, 'consent_messages' => $this->chance(60), 'visit_date' => $d->toDateString(), 'status' => $status === 'integrated' ? 'new' : $status,
                'source' => $src, 'responsible_id' => $status === 'new' && $i % 2 ? null : $this->pick($resp)->id,
            ]);
            $this->backdate('aero_church_visits', $v->id, ['created_at' => $d, 'updated_at' => $d]);

            if (in_array($status, ['contacted', 'invited', 'integrated'])) {
                $c = $this->make(VisitContact::class, ['visit_id' => $v->id, 'channel' => $this->pick($channels), 'note' => 'Se conversó sobre su visita y se resolvieron dudas.', 'next_action' => 'Invitar a la próxima reunión', 'next_action_date' => $status === 'contacted' ? $this->today->copy()->subDays(mt_rand(1, 3))->toDateString() : $this->today->copy()->addDays(mt_rand(2, 6))->toDateString()]);
                DB::table('aero_church_visits')->where('id', $v->id)->update(['status' => $status === 'integrated' ? 'new' : $status, 'next_action_date' => $c->next_action_date]);
                $this->backdate('aero_church_visit_contacts', $c->id, ['created_at' => $d->copy()->addDay()]);
            }
            if ($status === 'integrated') {
                // Esas dos personas ya existen como miembros nuevos (arriba): se enlaza sin duplicarlas.
                $member = $this->m[$name === 'Gonzalo Rivero' ? 'x0' : 'x1'];
                DB::table('aero_church_visits')->where('id', $v->id)->update(['status' => 'integrated', 'member_id' => $member->id, 'next_action_date' => null]);
            }
        }
    }

    // ───────────────────────── oración ─────────────────────────

    protected function prayers(): void
    {
        $rows = [
            ['Por la salud de mi madre, que está internada.', 'Familia Condori', true, true, 'in_prayer', 4],
            ['Agradecemos por el trabajo que nos dio Dios.', 'Anónimo', false, false, 'answered', 25],
            ['Por mi matrimonio, estamos pasando un tiempo difícil.', null, true, true, 'open', 2],
            ['Por los exámenes de mi hijo en la universidad.', 'Rosa V.', false, false, 'open', 6],
            ['Dirección para el nuevo proyecto de la iglesia.', 'Equipo de liderazgo', false, false, 'in_prayer', 12],
            ['Por mi hermano que se fue del país.', null, true, false, 'closed', 40],
        ];
        foreach ($rows as [$text, $name, $private, $contact, $status, $ago]) {
            $d = $this->today->copy()->subDays($ago);
            $p = $this->make(Prayer::class, ['branch_id' => $this->branch['c']->id, 'name' => $name, 'phone' => null, 'request' => $text, 'is_private' => $private, 'wants_contact' => $contact, 'consent_data' => true, 'status' => $status, 'source' => $name ? 'web' : 'manual']);
            $this->backdate('aero_church_prayers', $p->id, ['created_at' => $d, 'updated_at' => $d]);
            if ($status === 'in_prayer') {
                $n = $this->make(PrayerNote::class, ['prayer_id' => $p->id, 'note' => 'Se oró con la familia el domingo y se hará seguimiento esta semana.']);
                $this->backdate('aero_church_prayer_notes', $n->id, ['created_at' => $d->copy()->addDay()]);
            }
        }
    }

    // ───────────────────────── comunicados (solo borradores) ─────────────────────────

    protected function announcements(): void
    {
        $this->make(Announcement::class, ['title' => 'Culto dominical', 'kind' => 'reminder', 'body' => 'Hola {nombre}, te esperamos este domingo a las 10:00 en nuestro culto. ¡Trae a un amigo!', 'audience' => ['statuses' => ['active', 'new']], 'status' => 'draft']);
        $this->make(Announcement::class, ['title' => 'Reunión de jóvenes', 'kind' => 'announcement', 'body' => 'Hola {nombre}, este viernes 19:30 tenemos reunión de jóvenes. ¡No faltes!', 'audience' => ['group_id' => $this->grp['jov']->id], 'status' => 'draft']);
        $this->make(Announcement::class, ['title' => 'Cambio de horario', 'kind' => 'schedule_change', 'body' => 'Aviso: la conferencia de familia cambia de horario. Revisa el calendario de la iglesia.', 'audience' => ['statuses' => ['active']], 'status' => 'draft']);
    }

    // ───────────────────────── contenido del sitio ─────────────────────────

    protected function content(): void
    {
        $s1 = $this->make(Series::class, ['name' => 'Fundamentos de la fe', 'description' => 'Las bases de la vida cristiana.']);
        $s2 = $this->make(Series::class, ['name' => 'Familias fuertes', 'description' => 'Principios bíblicos para el hogar.']);
        $bunny = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';   // video de muestra de la Fundación Blender (libre)

        $sermons = [
            ['La gracia que nos sostiene', 'Pastor Roberto Salinas', 'Gracia', $s1, 'public', $bunny], ['Fe en medio de la prueba', 'Pastor Roberto Salinas', 'Fe', $s1, 'public', null],
            ['Orar sin cesar', 'Hna. Esther Villca', 'Oración', $s1, 'public', null], ['El hogar como santuario', 'Pastor Roberto Salinas', 'Familia', $s2, 'public', $bunny],
            ['Padres que bendicen', 'Pastor Carlos Condori', 'Familia', $s2, 'members', null], ['Perdonar para sanar', 'Hna. Esther Villca', 'Perdón', $s2, 'public', null],
            ['Servir con alegría', 'Pastor Carlos Condori', 'Servicio', null, 'members', null], ['Una iglesia unida', 'Pastor Roberto Salinas', 'Comunidad', null, 'public', null],
        ];
        foreach ($sermons as $i => [$title, $by, $topic, $serie, $vis, $media]) {
            $d = $this->sundayAgo($i + 1);
            $this->make(Sermon::class, ['series_id' => $serie?->id, 'title' => $title, 'slug' => Str::slug($title), 'preacher' => $by, 'topic' => $topic, 'preached_on' => $d->toDateString(), 'summary' => "Mensaje del domingo sobre «{$title}». Resumen de demostración con los puntos principales de la enseñanza.", 'media_url' => $media, 'visibility' => $vis, 'is_published' => true]);
        }

        $posts = [
            ['Bienvenidos a nuestra nueva página', 'public', true, 'news', -2], ['Campaña para el templo nuevo', 'public', false, 'news', -10],
            ['Reunión de líderes (solo líderes y miembros)', 'members', false, 'notice', -4], ['Horario especial por feriado', 'public', false, 'notice', -1],
        ];
        foreach ($posts as [$title, $vis, $pin, $kind, $off]) {
            $this->make(Post::class, ['title' => $title, 'slug' => Str::slug($title), 'body' => "Texto de demostración de la publicación «{$title}». Aquí va el contenido completo de la noticia o comunicado.", 'kind' => $kind, 'visibility' => $vis, 'is_published' => true, 'is_pinned' => $pin, 'published_at' => $this->today->copy()->addDays($off)]);
        }

        foreach ([['Guía de estudio: Fundamentos', 'study', 'public'], ['Manual de célula', 'group_guide', 'members'], ['Cuaderno de trabajo para niños', 'ministry', 'public']] as [$title, $kind, $vis]) {
            $res = $this->make(Resource::class, ['title' => $title, 'description' => 'Material de demostración (PDF de ejemplo).', 'kind' => $kind, 'visibility' => $vis, 'is_published' => true]);
            $this->attachPdf($res, $title);
        }
    }

    protected function attachPdf(Resource $res, string $title): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml('<h1>' . e($title) . '</h1><p>Documento de demostración generado por <code>church:demo</code>. Reemplázalo por tu material real.</p>', 'UTF-8');
        $pdf->render();

        $tmp = temp_path('church-demo-' . Str::random(8) . '.pdf');
        file_put_contents($tmp, $pdf->output());
        $f = new \System\Models\File();
        $f->is_public = false;                     // privado: solo se sirve por la ruta que verifica visibilidad
        $f->fromFile($tmp, Str::slug($title) . '.pdf');
        $res->file = $f;
        $res->save();
        @unlink($tmp);
    }

    // ───────────────────────── configuración ─────────────────────────

    protected function settings(): void
    {
        $s = ChurchSettings::forTenant($this->tid);
        $public = (bool) $this->option('public');
        $s->forceFill([
            'live_title' => $s->live_title ?: 'Culto dominical en vivo', 'live_schedule' => $s->live_schedule ?: "Domingos 10:00 y 18:00",
            'live_url' => $s->live_url ?: 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'welcome_text' => $s->welcome_text ?: 'Nos alegra que nos visites. Déjanos tus datos y te saludaremos.',
            'site_enabled' => $public, 'portal_enabled' => $public, 'welcome_enabled' => $public, 'prayer_enabled' => $public,
            'birthday_enabled' => false, 'notify_event_changes' => false,
        ])->save();
    }

    // ───────────────────────── limpieza y resumen ─────────────────────────

    protected function clean(): void
    {
        if (!is_file($this->manifest())) {
            $this->line('No hay datos de demostración que borrar en el tenant ' . $this->tid . '.');

            return;
        }

        Audit::$muted = true;
        $made = json_decode(file_get_contents($this->manifest()), true) ?: [];
        $order = ['aero_church_attendances', 'aero_church_meetings', 'aero_church_event_registrations', 'aero_church_events', 'aero_church_prayer_notes', 'aero_church_prayers',
            'aero_church_visit_contacts', 'aero_church_visits', 'aero_church_announcements', 'aero_church_contributions', 'aero_church_funds', 'aero_church_group_members',
            'aero_church_groups', 'aero_church_resources', 'aero_church_posts', 'aero_church_sermons', 'aero_church_series', 'aero_church_members', 'aero_church_families', 'aero_church_branches'];
        $n = 0;

        DB::transaction(function () use ($made, $order, &$n) {
            foreach ($order as $table) {
                $ids = array_values(array_unique($made[$table] ?? []));
                if (!$ids) {
                    continue;
                }
                if ($table === 'aero_church_resources') {
                    foreach (Resource::whereIn('id', $ids)->get() as $r) {
                        $r->file?->delete();
                    }
                }
                // Solo por id y siempre dentro de este tenant: nunca `where tenant_id` a secas.
                $n += DB::table($table)->where('tenant_id', $this->tid)->whereIn('id', $ids)->delete();
            }
            if (!Contribution::where('tenant_id', $this->tid)->exists()) {
                DB::table('aero_church_settings')->where('tenant_id', $this->tid)->update(['receipt_counter' => 0]);
            }
        });

        @unlink($this->manifest());
        $this->info("Borrados $n registros de demostración del tenant {$this->tid}.");
    }

    protected function summary(): void
    {
        $this->info('Datos de demostración cargados en el tenant ' . $this->tid . ':');
        foreach (collect($this->made)->map(fn ($a) => count($a))->sortKeys() as $table => $n) {
            $this->line(sprintf('  %-38s %d', str_replace('aero_church_', '', $table), $n));
        }
        $this->line($this->option('public') ? 'Sitio público, portal y formularios ACTIVADOS.' : 'Sitio público, portal y formularios APAGADOS (actívalos en Configuración).');
        $this->line('Sin teléfonos, sin mensajes, sin avisos y sin asientos contables.');
    }
}
