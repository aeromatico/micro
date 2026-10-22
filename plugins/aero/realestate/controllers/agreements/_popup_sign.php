<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Registrar firma</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Fecha de firma</label><input type="date" name="signed_on" class="form-control" value="<?= e(now()->format('Y-m-d')) ?>"><p class="help-block">Después puedes subir el escaneo firmado en la pestaña «Firmado».</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onSign" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Registrar firma</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
