<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Reabrir para corregir</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Motivo</label><textarea name="reason" class="form-control" rows="3" required></textarea><p class="help-block">Vuelve a borrador y deberá aprobarse de nuevo.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onReopen" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Reabrir</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
