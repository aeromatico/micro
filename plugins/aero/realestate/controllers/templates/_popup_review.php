<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Registrar revisión legal</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Abogado o estudio que revisó</label><input type="text" name="by" class="form-control" required maxlength="255"></div>
        <div class="form-group"><label>Observaciones (opcional)</label><textarea name="notes" class="form-control" rows="3"></textarea><p class="help-block">Queda registrada para esta versión exacta del texto. Si se edita el texto, hay que volver a revisarla.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onReview" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Registrar revisión</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
