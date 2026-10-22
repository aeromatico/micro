<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $c->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Resolver disputa</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Cómo se resolvió (opcional)</label><textarea name="note" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onResolve" data-request-flash data-request-validate data-hotkey="ctrl+enter, cmd+enter">Resolver</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
