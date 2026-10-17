<?php
use Aero\Plan\Classes\Stages;

$missing = $step->status === 'locked' ? [] : Stages::missing($step->number, $step->answers ?? []);
?>
<?php if ($editableClient): ?>
    <button type="button" class="btn btn-default" data-request="onSave">Guardar y salir</button>
    <button type="button" class="btn btn-primary" data-request="onSubmit" <?= $missing ? 'disabled' : '' ?>>Enviar a revisión</button>
<?php endif ?>
<?php if ($editableTeam): ?>
    <button type="button" class="btn btn-default" data-request="onSaveTeam">Guardar mis secciones</button>
<?php endif ?>
<?php if ($isTeam && $plan->status === 'active' && $step->status === 'review'): ?>
    <button type="button" class="btn btn-default" data-request="onChanges">Pedir cambios</button>
    <button type="button" class="btn btn-primary" data-request="onApprove"><?= $view < 5 ? 'Aprobar y abrir el paso ' . ($view + 1) : 'Aprobar y cerrar el plan' ?></button>
<?php endif ?>
