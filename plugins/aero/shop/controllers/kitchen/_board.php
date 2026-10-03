<?php
$types = \Aero\Shop\Models\ShopSettings::ORDER_TYPES;
$icons = ['dine_in' => 'utensils', 'pickup' => 'bag', 'delivery' => 'bike'];
$empty = ['new' => ['inbox', 'Sin pedidos nuevos'], 'preparing' => ['flame', 'Nada en preparación'], 'ready' => ['check-circle', 'Nada esperando entrega']];
$color = ['new' => 'var(--kx-new)', 'preparing' => 'var(--kx-prep)', 'ready' => 'var(--kx-ready)'];

$counts = ['all' => 0, 'dine_in' => 0, 'pickup' => 0, 'delivery' => 0];
foreach ($columns as $col) {
    foreach ($col['orders'] as $o) {
        $counts['all']++;
        if (isset($counts[$o->order_type])) $counts[$o->order_type]++;
    }
}
?>
<div data-new-ids="<?= e(json_encode($newIds)) ?>" data-server-ts="<?= time() ?>" hidden></div>

<div class="kx-bar" role="toolbar" aria-label="Filtrar por tipo de pedido">
    <button type="button" class="kx-chip" data-f="all">Todos <b><?= $counts['all'] ?></b></button>
    <?php foreach ($types as $key => $label): ?>
        <button type="button" class="kx-chip" data-f="<?= $key ?>"><?= \Aero\Shop\Classes\KitchenIcons::svg($icons[$key]) ?> <?= e($label) ?> <b><?= $counts[$key] ?></b></button>
    <?php endforeach ?>
    <span class="kx-spacer"></span>
    <button type="button" class="kx-btn kx-busy<?= $busy ? ' is-on' : '' ?>" data-request="onToggleBusy"
            title="Suma minutos al tiempo estimado de los pedidos nuevos" aria-pressed="<?= $busy ? 'true' : 'false' ?>">
        <?= \Aero\Shop\Classes\KitchenIcons::svg('flame') ?> Modo ocupado<?= $busy ? ' activo (+' . (int) $busyExtra . ' min)' : '' ?>
    </button>
</div>

<div class="kx-cols">
<?php foreach ($columns as $key => $col): ?>
    <section class="kx-col" aria-label="<?= e($col['label']) ?>">
        <div class="kx-colhead" style="--c:<?= $color[$key] ?>">
            <?= e($col['label']) ?> <span class="n"><?= $col['orders']->count() ?></span>
        </div>

        <?php foreach ($col['orders'] as $o):
            $limit = $key === 'ready' ? 9999 : max(10, (int) ($o->items->map(fn ($i) => (int) ($i->product?->prep_minutes))->max() ?: 20));
        ?>
            <article class="k-card" data-id="<?= $o->id ?>" data-st="<?= $key ?>" data-type="<?= e($o->order_type) ?>">
                <div class="k-top">
                    <span class="k-num"><?= e($o->order_number) ?></span>
                    <span class="k-timer" data-ts="<?= $o->created_at->timestamp ?>" data-limit="<?= $limit ?>">…</span>
                </div>

                <div class="k-meta">
                    <span class="k-pill <?= e($o->order_type) ?>"><?= \Aero\Shop\Classes\KitchenIcons::svg($icons[$o->order_type] ?? 'utensils') ?> <?= e($types[$o->order_type] ?? '—') ?></span>
                    <?php if ($o->table_label): ?><span class="k-pill table">Mesa <?= e($o->table_label) ?></span><?php endif ?>
                </div>

                <?php if ($o->scheduled_for): ?>
                    <div class="k-sched"><?= \Aero\Shop\Classes\KitchenIcons::svg('clock') ?> Programado para <?= e($o->scheduled_for->format('d/m H:i')) ?></div>
                <?php endif ?>

                <div class="k-cust">
                    <?= \Aero\Shop\Classes\KitchenIcons::svg('user') ?> <?= e($o->customer?->full_name) ?>
                    <?php if ($o->customer?->phone): ?> · <a href="tel:<?= e($o->customer->phone) ?>"><?= e($o->customer->phone) ?></a><?php endif ?>
                </div>

                <ul class="k-items">
                    <?php foreach ($o->items as $item): ?>
                        <li>
                            <span class="k-qty"><?= (int) $item->quantity ?></span>
                            <div>
                                <div class="k-name"><?= e($item->product_name_snapshot) ?><?php if ($item->variant_label_snapshot): ?> <small>(<?= e($item->variant_label_snapshot) ?>)</small><?php endif ?></div>
                                <?php if ($item->modifiers): ?>
                                    <div class="k-extras">
                                        <?php foreach ($item->modifiers as $m): ?><span class="k-extra">+ <?= e($m['name']) ?></span><?php endforeach ?>
                                    </div>
                                <?php endif ?>
                                <?php if ($item->note): ?><div class="k-note"><?= \Aero\Shop\Classes\KitchenIcons::svg('sticky-note') ?> <?= e($item->note) ?></div><?php endif ?>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>

                <?php if ($o->customer_notes): ?><div class="k-onote"><?= \Aero\Shop\Classes\KitchenIcons::svg('comment') ?> <?= e($o->customer_notes) ?></div><?php endif ?>

                <?php if ($key === 'new'): $sg = (int) ($suggest[$o->id] ?? 20); ?>
                    <div class="k-eta" data-id="<?= $o->id ?>" data-base="<?= $sg ?>">
                        <div class="k-eta-label">
                            <?= \Aero\Shop\Classes\KitchenIcons::svg('clock') ?>
                            <?= $o->order_type === 'delivery' ? 'Llegada al cliente en' : ($o->order_type === 'pickup' ? 'Listo para recoger en' : 'Listo para servir en') ?>
                            <?php if ($o->scheduled_for): ?><small>Programado: empezar a las <span class="k-start-at" data-sched="<?= $o->scheduled_for->timestamp ?>">…</span></small><?php endif ?>
                        </div>
                        <div class="k-eta-ctl">
                            <button type="button" class="k-step" data-step="-5" title="Restar 5 minutos" aria-label="Restar 5 minutos"><?= \Aero\Shop\Classes\KitchenIcons::svg('minus') ?></button>
                            <strong class="k-eta-val"><?= $sg ?> min</strong>
                            <button type="button" class="k-step" data-step="5" title="Sumar 5 minutos" aria-label="Sumar 5 minutos"><?= \Aero\Shop\Classes\KitchenIcons::svg('plus') ?></button>
                            <button type="button" class="k-step k-reset" data-reset="1" title="Volver al tiempo sugerido (<?= $sg ?> min)" aria-label="Volver al tiempo sugerido"><?= \Aero\Shop\Classes\KitchenIcons::svg('rotate') ?></button>
                        </div>
                        <div class="k-eta-hint">Sugerido <?= $sg ?> min según el plato y la carga de cocina</div>
                    </div>
                <?php elseif ($key === 'preparing'):
                    $due = $o->promised_at; $late = $due && $due->isPast(); ?>
                    <div class="k-eta k-eta-set<?= $late ? ' is-late' : '' ?>" <?= $due ? 'data-due="' . $due->timestamp . '"' : '' ?>>
                        <div class="k-eta-label">
                            <?= \Aero\Shop\Classes\KitchenIcons::svg('clock') ?>
                            <?php if ($due): ?>
                                <?= $o->order_type === 'delivery' ? 'Llegada prometida' : 'Listo a las' ?>
                                <strong><?= e($due->timezone($tz)->format('H:i')) ?></strong>
                                <small class="k-left">…</small>
                            <?php else: ?>
                                Sin hora prometida al cliente
                            <?php endif ?>
                        </div>
                        <div class="k-eta-ctl">
                            <span class="k-eta-cap"><?= $due ? 'Avisar retraso' : 'Fijar en' ?></span>
                            <button type="button" class="k-step k-plus" title="<?= $due ? 'Retrasar 5 minutos' : 'Prometer 5 minutos desde ahora' ?>"
                                    data-request="onExtend" data-request-data="order_id: <?= $o->id ?>, minutes: 5"><?= \Aero\Shop\Classes\KitchenIcons::svg('plus') ?> 5 min</button>
                            <button type="button" class="k-step k-plus" title="<?= $due ? 'Retrasar 10 minutos' : 'Prometer 10 minutos desde ahora' ?>"
                                    data-request="onExtend" data-request-data="order_id: <?= $o->id ?>, minutes: 10"><?= \Aero\Shop\Classes\KitchenIcons::svg('plus') ?> 10 min</button>
                            <button type="button" class="k-step k-plus" title="<?= $due ? 'Retrasar 15 minutos' : 'Prometer 15 minutos desde ahora' ?>"
                                    data-request="onExtend" data-request-data="order_id: <?= $o->id ?>, minutes: 15"><?= \Aero\Shop\Classes\KitchenIcons::svg('plus') ?> 15 min</button>
                        </div>
                    </div>
                <?php endif ?>

                <div class="k-actions">
                    <?php if (isset($prevOf[$key])): ?>
                        <button type="button" class="k-undo" title="Regresar este pedido a «<?= e(\Aero\Shop\Models\Order::KITCHEN_STATUSES[$prevOf[$key]]) ?>»"
                                aria-label="Regresar a <?= e(\Aero\Shop\Models\Order::KITCHEN_STATUSES[$prevOf[$key]]) ?>"
                                data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: '<?= $prevOf[$key] ?>'"><?= \Aero\Shop\Classes\KitchenIcons::svg('arrow-left') ?></button>
                    <?php endif ?>
                    <?php if ($key === 'new'): ?>
                    <button type="button" class="k-go k-start" data-id="<?= $o->id ?>">
                    <?php else: ?>
                    <button type="button" class="k-go" data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: '<?= $col['next'] ?>'">
                    <?php endif ?>
                        <?= e($col['action']) ?> <?= \Aero\Shop\Classes\KitchenIcons::svg('chevron-right') ?>
                    </button>
                </div>
            </article>
        <?php endforeach ?>

        <?php if ($col['orders']->isEmpty()): ?>
            <div class="k-empty"><?= \Aero\Shop\Classes\KitchenIcons::svg($empty[$key][0]) ?><?= e($empty[$key][1]) ?></div>
        <?php endif ?>
    </section>
<?php endforeach ?>
</div>

<?php if ($done->count()): ?>
    <div class="kx-done">
        <h5>Entregados (últimas 3 h)</h5>
        <?php foreach ($done as $o): ?>
            <span>
                <?= \Aero\Shop\Classes\KitchenIcons::svg('check') ?> <?= e($o->order_number) ?><?= $o->table_label ? ' · Mesa ' . e($o->table_label) : '' ?>
                <a href="#" data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: 'ready'" title="Reabrir" aria-label="Reabrir"><?= \Aero\Shop\Classes\KitchenIcons::svg('rotate') ?></a>
            </span>
        <?php endforeach ?>
    </div>
<?php endif ?>
