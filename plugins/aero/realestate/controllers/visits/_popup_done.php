<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Visita realizada</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Cómo salió (opcional)</label><textarea name="notes" class="form-control" rows="4"></textarea><p class="help-block">Queda en el historial del cliente.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onDone" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Guardar</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
