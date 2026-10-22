<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Renovar contrato</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Meses a extender</label><input type="number" name="months" class="form-control" min="1" value="12"><p class="help-block">Hoy vence el <?= e($m->end_date?->format('d/m/Y')) ?>.</p></div>
        <div class="form-group"><label>Nuevo monto (opcional)</label><input type="number" step="any" name="amount" class="form-control" placeholder="<?= e($m->amount) ?>"></div>
        <div class="form-group"><label>Nota</label><textarea name="note" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onRenew" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Renovar</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
