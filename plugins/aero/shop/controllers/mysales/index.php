<div class="padded-container">
    <?= $this->makePartial('~/plugins/aero/shop/controllers/shared/_date_filter.php', ['from' => $from, 'to' => $to]) ?>
    <?= $this->makePartial('~/plugins/aero/shop/controllers/shared/_balance_cards.php', ['balance' => $balance]) ?>
    <?= $this->makePartial('~/plugins/aero/shop/controllers/shared/_orders_table.php', ['orders' => $orders]) ?>
</div>
