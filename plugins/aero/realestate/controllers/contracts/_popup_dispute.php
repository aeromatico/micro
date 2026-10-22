<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $c->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Devolución en disputa</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Motivo</label><textarea name="note" class="form-control" rows="4" required></textarea></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onDispute" data-request-flash data-request-validate data-hotkey="ctrl+enter, cmd+enter">Marcar en disputa</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
