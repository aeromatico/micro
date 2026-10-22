<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $c->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Activar contrato</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Fecha de inicio</label><input type="date" name="start_date" class="form-control" value="<?= e(($c->start_date ?: now())->format('Y-m-d')) ?>"><p class="help-block">Exige el capital completo entregado (hoy: <?= number_format((float) $c->delivered_amount, 2, ',', '.') ?> de <?= number_format((float) $c->capital, 2, ',', '.') ?> <?= e($c->currency) ?>). El vencimiento se calcula con el plazo.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onActivate" data-request-flash data-request-validate data-hotkey="ctrl+enter, cmd+enter">Activar</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
