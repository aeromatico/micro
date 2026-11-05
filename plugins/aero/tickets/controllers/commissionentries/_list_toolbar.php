<?php
$sum = fn ($s) => (float) \Aero\Tickets\Models\CommissionEntry::where('status', $s)->sum('commission_amount');
$f = fn ($v) => \Aero\Tickets\Classes\Currency::format($v);
?>
<div data-control="toolbar"><span class="text-muted">Por liquidar: <strong><?= e($f($sum('accrued'))) ?></strong> · Liquidadas: <strong><?= e($f($sum('settled'))) ?></strong> · Anuladas: <strong><?= e($f($sum('void'))) ?></strong>
 — Cada fila guarda las condiciones comerciales aplicadas a su orden.</span></div>
