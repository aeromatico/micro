<?= Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/shop/sellers') ?>">Comerciantes</a></li>
        <li><?= e($seller->display_name) ?></li>
    </ul>
<?= Block::endPut() ?>

<div class="padded-container">
    <p style="margin-bottom:14px">
        <a class="btn btn-default" href="<?= Backend::url('aero/shop/sellers/update/' . $seller->id) ?>">Editar comerciante</a>
        <span style="margin-left:10px;color:#6b7280">
            Comisión: <?= $seller->commission_type === 'fixed' ? 'Bs ' . number_format($seller->commission_value, 2) . ' por pedido' : (float) $seller->commission_value . ' %' ?>
        </span>
    </p>
    <?= $this->makePartial('~/plugins/aero/shop/controllers/shared/_date_filter.php', ['from' => $from, 'to' => $to]) ?>
    <?= $this->makePartial('~/plugins/aero/shop/controllers/shared/_balance_cards.php', ['balance' => $balance]) ?>
    <?= $this->makePartial('~/plugins/aero/shop/controllers/shared/_orders_table.php', ['orders' => $orders]) ?>
</div>
