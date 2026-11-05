<?php $q = \Aero\Tickets\Classes\Pricing::quote($formModel); $f = fn ($v) => \Aero\Tickets\Classes\Currency::format($v, $q['currency']); ?>
<?php if ($formModel->kind === 'paid'): ?>
<table class="table" style="max-width:420px">
    <tr><td>Precio base</td><td class="text-right"><?= e($f($q['base'])) ?></td></tr>
    <tr><td>Comisión por servicio (<?= $q['fee_payer'] === 'buyer' ? 'la paga el comprador' : 'la paga el organizador' ?>)</td><td class="text-right"><?= e($f($q['fee'])) ?></td></tr>
    <tr><td><strong>Precio final al comprador</strong></td><td class="text-right"><strong><?= e($f($q['total'])) ?></strong></td></tr>
    <tr><td>Recibe el organizador por entrada</td><td class="text-right"><?= e($f($q['net'])) ?></td></tr>
</table>
<?php endif ?>
