<?php $b = \Aero\Shop\Classes\SellerBalance::for($record->tenant_id, $record->id); ?>
<strong><?= number_format($b['net'], 2) ?></strong>
<small style="opacity:.6"> · <?= $b['orders'] ?> ped.</small>
