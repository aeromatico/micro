<?php
$types = \Aero\Shop\Models\ShopSettings::ORDER_TYPES;
$icons = ['dine_in' => 'icon-cutlery', 'pickup' => 'icon-shopping-bag', 'delivery' => 'icon-motorcycle'];
$empty = ['new' => ['icon-inbox', 'Sin pedidos nuevos'], 'preparing' => ['icon-fire', 'Nada en preparación'], 'ready' => ['icon-check-circle', 'Nada esperando entrega']];
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
        <button type="button" class="kx-chip" data-f="<?= $key ?>"><i class="<?= $icons[$key] ?>"></i> <?= e($label) ?> <b><?= $counts[$key] ?></b></button>
    <?php endforeach ?>
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
                    <span class="k-pill <?= e($o->order_type) ?>"><i class="<?= $icons[$o->order_type] ?? 'icon-tag' ?>"></i> <?= e($types[$o->order_type] ?? '—') ?></span>
                    <?php if ($o->table_label): ?><span class="k-pill table">Mesa <?= e($o->table_label) ?></span><?php endif ?>
                </div>

                <?php if ($o->scheduled_for): ?>
                    <div class="k-sched"><i class="icon-clock"></i> Programado para <?= e($o->scheduled_for->format('d/m H:i')) ?></div>
                <?php endif ?>

                <div class="k-cust">
                    <i class="icon-user"></i> <?= e($o->customer?->full_name) ?>
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
                                <?php if ($item->note): ?><div class="k-note"><i class="icon-sticky-note"></i> <?= e($item->note) ?></div><?php endif ?>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>

                <?php if ($o->customer_notes): ?><div class="k-onote"><i class="icon-comment"></i> <?= e($o->customer_notes) ?></div><?php endif ?>

                <div class="k-actions">
                    <?php if (isset($prevOf[$key])): ?>
                        <button type="button" class="k-undo" title="Regresar al paso anterior" aria-label="Regresar"
                                data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: '<?= $prevOf[$key] ?>'"><i class="icon-rotate-left"></i></button>
                    <?php endif ?>
                    <button type="button" class="k-go" data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: '<?= $col['next'] ?>'">
                        <?= e($col['action']) ?> <i class="icon-angle-right"></i>
                    </button>
                </div>
            </article>
        <?php endforeach ?>

        <?php if ($col['orders']->isEmpty()): ?>
            <div class="k-empty"><i class="<?= $empty[$key][0] ?>"></i><?= e($empty[$key][1]) ?></div>
        <?php endif ?>
    </section>
<?php endforeach ?>
</div>

<?php if ($done->count()): ?>
    <div class="kx-done">
        <h5>Entregados (últimas 3 h)</h5>
        <?php foreach ($done as $o): ?>
            <span>
                <i class="icon-check"></i> <?= e($o->order_number) ?><?= $o->table_label ? ' · Mesa ' . e($o->table_label) : '' ?>
                <a href="#" data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: 'ready'" title="Reabrir" aria-label="Reabrir"><i class="icon-rotate-left"></i></a>
            </span>
        <?php endforeach ?>
    </div>
<?php endif ?>
