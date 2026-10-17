<?php
use Aero\Plan\Classes\Stages;

$answers = $step->answers ?? [];
$total = Stages::requiredCount($step->number, $answers);
$miss = count(Stages::missing($step->number, $answers));
?>
<div class="aplan-prog"><?= $total - $miss ?> de <?= $total ?> obligatorias completas</div>
<div class="aplan-saved">Guardado <?= $step->updated_at ? $step->updated_at->format('d/m H:i') : '' ?></div>
