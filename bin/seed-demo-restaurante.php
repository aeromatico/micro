<?php
/**
 * Actividad ficticia para el tenant demo «restaurante» (handle `restaurante`): pedidos de todo tipo para que la
 * carta, la cocina, la caja y los reportes se vean trabajando. Pocos, los necesarios para una demo.
 *
 *   sudo -u www /www/server/php/84/bin/php bin/seed-demo-restaurante.php          # crea (si no existe ya)
 *   sudo -u www /www/server/php/84/bin/php bin/seed-demo-restaurante.php --clean   # borra SOLO lo que creó este script
 *   sudo -u www /www/server/php/84/bin/php bin/seed-demo-restaurante.php --reset   # --clean y vuelve a crear
 *
 * Todo lo sembrado lleva `notes = 'demo-restaurante'` (pedidos) o correo `demo-*@sin-correo.invalid` (clientes, sin
 * celular). Los pedidos se crean como «pos» para que no se dispare ningún aviso real y luego se ajusta su origen.
 */

use Aero\Shop\Classes\DailyNumber;
use Aero\Shop\Classes\OrderService;
use Aero\Shop\Models\Customer;
use Aero\Shop\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const TAG = 'demo-restaurante';
$tenantId = (int) DB::table('aero_sites_tenants')->where('handle', 'restaurante')->value('id');
if (!$tenantId) {
    fwrite(STDERR, "No existe el tenant «restaurante».\n");
    exit(1);
}
$tz = DB::table('aero_shop_settings')->where('tenant_id', $tenantId)->value('timezone') ?: 'America/La_Paz';
$arg = $argv[1] ?? '';

$clean = function () use ($tenantId) {
    $ids = Order::where('tenant_id', $tenantId)->where('notes', TAG)->pluck('id');
    DB::table('aero_shop_order_items')->whereIn('order_id', $ids)->delete();
    DB::table('aero_shop_order_status_history')->whereIn('order_id', $ids)->delete();
    $addr = Order::whereIn('id', $ids)->pluck('shipping_address_id')->filter();
    Order::whereIn('id', $ids)->delete();
    DB::table('aero_shop_addresses')->whereIn('id', $addr)->delete();
    $cust = Customer::where('tenant_id', $tenantId)->where('email', 'like', 'demo-%@sin-correo.invalid')->pluck('id');
    $left = DB::table('aero_shop_orders')->whereIn('customer_id', $cust)->pluck('customer_id');
    DB::table('aero_shop_customers')->where('tenant_id', $tenantId)->whereIn('id', $cust->diff($left))->delete();
    echo "Borrados: {$ids->count()} pedidos demo.\n";
};

if ($arg === '--clean') {
    $clean();
    exit(0);
}
if ($arg === '--reset') {
    $clean();
}
if (Order::where('tenant_id', $tenantId)->where('notes', TAG)->exists()) {
    echo "Ya hay pedidos demo (usa --reset para recrearlos).\n";
    exit(0);
}

// --- catálogo del demo (ids del tenant) y opciones obligatorias de cada plato
$M = [
    'limonada'  => [45, ['1DriENCI']], 'limonada_jarra' => [45, ['NxDZP5gA', 'c7L65k6m']], 'cafe' => [46, ['5tHbgZpq']],
    'saltena'   => [37, ['3LLj96wl']], 'saltena_pic' => [37, ['xqp5VI9c']], 'empanada' => [38, ['jprnlzcU']],
    'papas'     => [39, ['sdS54xuZ']], 'papas_g' => [39, ['3NCABs1w', 'xjTSQg7n']], 'silpancho' => [40, ['zFfJI0oC']],
    'pique'     => [41, ['JdBrPWaT', 'sAwIGw2G']], 'pique_dos' => [41, ['AIMhSzUX', 'vEOFhwwy']], 'cesar' => [42, ['pYpa0ddP']],
    'burger'    => [43, ['rscCVGHl', 'xpLFFENw']], 'burger_tocino' => [43, ['gWzLsjNt', 'WVZhWHUu']], 'pizza' => [44, ['K0E2FnEy', 'PAHtbepP']],
    'tiramisu'  => [47, []],
];
$it = fn (string $k, int $q = 1, ?string $note = null) => ['product_id' => $M[$k][0], 'quantity' => $q, 'modifiers' => $M[$k][1], 'note' => $note];
$mins = fn (int $m) => now()->subMinutes($m);
$at = fn (int $daysAgo, string $hm) => Carbon::parse(now($tz)->subDays($daysAgo)->toDateString() . ' ' . $hm, $tz)->utc();

// Ayer (para los reportes): 6 pedidos entregados y cobrados y 1 cancelado.
$yesterday = [
    ['Ana',    'dine_in',  '2', 'pos',      '13:05', [$it('pique'), $it('limonada', 2)],                              'delivered'],
    ['Beto',   'dine_in',  '5', 'web',      '13:40', [$it('burger'), $it('papas'), $it('cafe')],                        'delivered'],
    ['Carla',  'pickup',   null, 'web',     '14:10', [$it('pizza'), $it('limonada')],                                  'delivered'],
    ['Diego',  'delivery', null, 'whatsapp', '20:05', [$it('pique_dos'), $it('limonada_jarra')],                         'delivered'],
    ['Elena',  'dine_in',  '1', 'pos',      '20:30', [$it('cesar'), $it('tiramisu', 2)],                                'delivered'],
    ['Fabián', 'pickup',   null, 'web',     '21:15', [$it('saltena', 4), $it('empanada', 2)],                           'delivered'],
    ['Gabriela', 'dine_in', '6', 'web',     '21:40', [$it('burger_tocino')],                                          'cancelled'],
];
// Hoy: de menor a mayor «hace N min»; cubre todos los tipos, orígenes y estados (por cobrar, pagado, retrasado, programado).
$today = [
    ['Hugo',    'dine_in',  '3', 'web',      110, [$it('silpancho'), $it('limonada')],                                  'delivered', true],
    ['Ines',    'dine_in',  '5', 'pos',      95,  [$it('pizza'), $it('limonada_jarra')],                                'delivered', true],
    ['Jorge',   'pickup',   null, 'web',     70,  [$it('saltena', 3), $it('cafe', 2)],                                  'delivered', true],
    ['Karen',   'delivery', null, 'whatsapp', 62, [$it('burger'), $it('papas_g')],                                      'delivered', true, [-16.5002, -68.1301]],
    ['Luis',    'dine_in',  '2', 'web',      24,  [$it('pique'), $it('cesar'), $it('limonada', 2)],                     'ready',     false],
    ['Marta',   'dine_in',  '1', 'pos',      21,  [$it('burger_tocino'), $it('papas')],                                 'preparing', false, null, 'ronda2'],
    ['Nico',    'delivery', null, 'web',     27,  [$it('pizza'), $it('saltena_pic', 2), $it('limonada')],               'preparing', false, [-16.5143, -68.1189], 'tarde'],
    ['Olga',    'dine_in',  '6', 'web',      4,   [$it('silpancho', 2), $it('limonada', 2)],                            'new',       false, null, 'nota'],
    ['Pablo',   'pickup',   null, 'whatsapp', 2,  [$it('empanada', 6), $it('cafe')],                                    'new',       false],
    ['Rosa',    'pickup',   null, 'web',     1,   [$it('cesar', 2), $it('tiramisu')],                                   'new',       false, null, 'programado'],
];

$svc = new OrderService();
$shipFor = fn (?array $ll) => $ll ? ['address_line1' => 'Dirección de ejemplo (demo)', 'city' => 'La Paz', 'country_code' => 'BO', 'latitude' => $ll[0], 'longitude' => $ll[1]] : null;
$make = function (string $who, string $type, ?string $table, array $items, array $extra = []) use ($svc, $tenantId, $shipFor) {
    $o = $svc->create($tenantId, $items,
        ['first_name' => $who, 'email' => 'demo-' . strtolower(Illuminate\Support\Str::ascii($who)) . '@sin-correo.invalid'], null,
        ['source' => 'pos', 'counter' => true, 'order_type' => $type, 'table_label' => $table, 'shipping' => $shipFor($extra['ll'] ?? null),
         'customer_notes' => $extra['note'] ?? null, 'scheduled_for' => $extra['scheduled'] ?? null]);

    return $o;
};
$settle = function (Order $o, string $source, \DateTimeInterface $created, string $kitchen, bool $paid, ?\DateTimeInterface $promised = null) {
    $f = ['notes' => TAG, 'source' => $source, 'created_at' => $created, 'updated_at' => $created, 'kitchen_status' => $kitchen === 'cancelled' ? null : $kitchen,
          'kitchen_updated_at' => $created->copy()->addMinutes(3)];
    if (in_array($kitchen, ['preparing', 'ready', 'delivered'], true)) {
        $f['accepted_at'] = $created->copy()->addMinutes(2);
        $f['promised_at'] = $promised ?: $created->copy()->addMinutes(22);
    }
    if (in_array($kitchen, ['ready', 'delivered'], true)) {
        $f['ready_at'] = $created->copy()->addMinutes(18);
    }
    if ($kitchen === 'delivered') {
        $f['status'] = 'fulfilled'; $f['fulfilled_at'] = $created->copy()->addMinutes(26);
    }
    if ($kitchen === 'cancelled') {
        $f['status'] = 'cancelled'; $f['cancelled_at'] = $created->copy()->addMinutes(5); $f['cancel_reason'] = 'El cliente no llegó (demo)';
    }
    if ($paid) {
        $f['paid_at'] = $created->copy()->addMinutes($kitchen === 'delivered' ? 28 : 3);
        if ($kitchen !== 'delivered') {
            $f['status'] = 'paid';
        }
    }
    DB::table('aero_shop_orders')->where('id', $o->id)->update(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->copy()->utc()->toDateTimeString() : $v, $f));
};

DB::beginTransaction();
try {
    // --- ayer: se crean primero y luego se les da el número de AYER (1, 2, 3…)
    $n = 0;
    foreach ($yesterday as [$who, $type, $table, $src, $hm, $items, $state]) {
        $n++;
        $created = $at(1, $hm);
        $o = $make($who, $type, $table, $items, ['ll' => $type === 'delivery' ? [-16.5090, -68.1250] : null]);
        $settle($o, $src, $created, $state === 'cancelled' ? 'cancelled' : 'delivered', $state !== 'cancelled');
        DB::table('aero_shop_orders')->where('id', $o->id)->update(['daily_number' => $n, 'daily_date' => $created->copy()->setTimezone($tz)->toDateString()]);
    }
    // el contador de hoy vuelve a 0: los pedidos de hoy empiezan en 1
    DB::table('aero_shop_daily_counters')->where('tenant_id', $tenantId)->where('day', DailyNumber::today($tenantId))->delete();

    foreach ($today as $row) {
        [$who, $type, $table, $src, $ago, $items, $state, $paid] = $row;
        $ll = $row[8] ?? null; $flag = $row[9] ?? null;
        $extra = ['ll' => $ll];
        if ($flag === 'nota') { $extra['note'] = 'Sin ají en el silpancho, por favor.'; }
        if ($flag === 'programado') { $extra['scheduled'] = now()->addMinutes(55); $extra['note'] = 'Para el almuerzo de la oficina.'; }
        $o = $make($who, $type, $table, $items, $extra);
        if ($flag === 'ronda2') {
            $svc->appendItems($o, [$it('limonada', 2), $it('tiramisu')]);
        }
        $created = $mins($ago);
        $promised = $flag === 'tarde' ? $created->copy()->addMinutes(18) : ($state === 'preparing' ? now()->addMinutes(7) : null);
        $settle($o, $src, $created, $state, (bool) $paid, $promised);
        if ($flag === 'programado') {
            DB::table('aero_shop_orders')->where('id', $o->id)->update(['scheduled_for' => $extra['scheduled']->utc()->toDateTimeString()]);
        }
    }
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
echo 'Listo: ' . Order::where('tenant_id', $tenantId)->where('notes', TAG)->count() . " pedidos demo.\n";
