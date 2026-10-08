<?php /** @var Aero\Pos\Controllers\Shifts $this */ ?>
<div class="padded-container">
    <p><a href="<?= Backend::url('aero/pos/shifts') ?>"><i class="icon-arrow-left"></i> Turnos</a></p>
    <?php if ($terminals->isEmpty()): ?>
        <div class="alert alert-warning">No hay cajas libres: todas tienen un turno abierto o están desactivadas.</div>
    <?php else: ?>
    <form data-request="onOpen" data-request-flash style="max-width:420px">
        <div class="form-group">
            <label>Caja</label>
            <select name="terminal_id" class="form-control custom-select">
                <?php foreach ($terminals as $t): ?><option value="<?= $t->id ?>"><?= e($t->name) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="form-group">
            <label>Efectivo inicial en caja</label>
            <input type="number" step="0.01" min="0" name="opening_cash" class="form-control" value="0">
            <p class="help-block">Lo que hay en la caja al empezar (el «cambio» del día).</p>
        </div>
        <button type="submit" class="btn btn-primary" data-load-indicator="Abriendo..."><i class="icon-check"></i> Abrir turno</button>
    </form>
    <?php endif ?>
</div>
