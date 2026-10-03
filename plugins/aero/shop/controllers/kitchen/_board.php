<?php
$orderTypes = \Aero\Shop\Models\ShopSettings::ORDER_TYPES;
$icons = ['dine_in' => '🍽️', 'pickup' => '🛍️', 'delivery' => '🛵'];
?>
<div data-new-ids="<?= e(json_encode($newIds)) ?>"></div>
<div style="display:grid;grid-template-columns:repeat(3,minmax(260px,1fr));gap:14px;align-items:start">
<?php foreach ($columns as $key => $col): ?>
    <div style="background:#f4f5f7;border-radius:8px;padding:10px;min-height:200px">
        <h4 style="margin:0 0 10px;display:flex;justify-content:space-between">
            <span><?= e($col['label']) ?></span>
            <span class="badge" style="background:#6b7280;color:#fff;border-radius:10px;padding:2px 8px"><?= $col['orders']->count() ?></span>
        </h4>
        <?php foreach ($col['orders'] as $o):
            $mins = (int) $o->created_at->diffInMinutes(now(), true);
            $late = $key !== 'ready' && $mins >= 20;
        ?>
            <div style="background:#fff;border-radius:8px;padding:12px;margin-bottom:10px;border-left:5px solid <?= $late ? '#dc2626' : ($key === 'new' ? '#2563eb' : ($key === 'preparing' ? '#f59e0b' : '#16a34a')) ?>;box-shadow:0 1px 3px rgba(0,0,0,.08)">
                <div style="display:flex;justify-content:space-between;font-weight:700">
                    <span><?= e($o->order_number) ?></span>
                    <span style="color:<?= $late ? '#dc2626' : '#6b7280' ?>"><?= $mins ?> min</span>
                </div>
                <div style="margin:4px 0;font-size:15px">
                    <?= $icons[$o->order_type] ?? '' ?> <strong><?= e($orderTypes[$o->order_type] ?? '—') ?></strong>
                    <?php if ($o->table_label): ?> · Mesa <strong><?= e($o->table_label) ?></strong><?php endif ?>
                </div>
                <?php if ($o->scheduled_for): ?>
                    <div style="color:#b45309;font-weight:600">⏰ Programado: <?= e($o->scheduled_for->format('d/m H:i')) ?></div>
                <?php endif ?>
                <div style="color:#6b7280;font-size:12px"><?= e($o->customer?->full_name) ?><?= $o->customer?->phone ? ' · ' . e($o->customer->phone) : '' ?></div>
                <ul style="list-style:none;padding:0;margin:8px 0">
                    <?php foreach ($o->items as $item): ?>
                        <li style="padding:4px 0;border-top:1px dashed #e5e7eb">
                            <strong><?= (int) $item->quantity ?>×</strong> <?= e($item->product_name_snapshot) ?>
                            <?php if ($item->variant_label_snapshot): ?><small>(<?= e($item->variant_label_snapshot) ?>)</small><?php endif ?>
                            <?php if ($item->modifiers): ?><div style="font-size:12px;color:#374151">+ <?= e(\Aero\Shop\Classes\RestaurantService::modifiersText($item->modifiers)) ?></div><?php endif ?>
                            <?php if ($item->note): ?><div style="font-size:12px;background:#fef3c7;padding:2px 6px;border-radius:4px">📝 <?= e($item->note) ?></div><?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
                <?php if ($o->customer_notes): ?><div style="font-size:12px;background:#fef3c7;padding:4px 6px;border-radius:4px;margin-bottom:8px">💬 <?= e($o->customer_notes) ?></div><?php endif ?>
                <div style="display:flex;gap:6px">
                    <?php if (isset($prevOf[$key])): ?>
                        <button type="button" class="btn btn-default btn-sm" data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: '<?= $prevOf[$key] ?>'" title="Regresar">↩</button>
                    <?php endif ?>
                    <button type="button" class="btn btn-primary btn-sm" style="flex:1" data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: '<?= $col['next'] ?>'"><?= e($col['action']) ?></button>
                </div>
            </div>
        <?php endforeach ?>
        <?php if ($col['orders']->isEmpty()): ?><p class="text-muted" style="text-align:center;margin-top:30px">Sin pedidos</p><?php endif ?>
    </div>
<?php endforeach ?>
</div>

<?php if ($done->count()): ?>
    <h5 style="margin:18px 0 8px;color:#6b7280">Entregados (últimas 3 h)</h5>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($done as $o): ?>
            <span style="background:#e5e7eb;border-radius:6px;padding:4px 10px;font-size:12px">
                <?= e($o->order_number) ?><?= $o->table_label ? ' · Mesa ' . e($o->table_label) : '' ?>
                <a href="#" data-request="onMove" data-request-data="order_id: <?= $o->id ?>, to: 'ready'" title="Reabrir" style="margin-left:6px">↩</a>
            </span>
        <?php endforeach ?>
    </div>
<?php endif ?>
