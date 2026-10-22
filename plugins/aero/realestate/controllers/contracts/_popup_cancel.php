<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $c->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Cancelar contrato</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Motivo</label><textarea name="reason" class="form-control" rows="3"></textarea><p class="help-block">Libera la propiedad y la oferta. Un contrato vigente no se cancela: se finaliza.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onCancel" data-request-flash data-request-validate data-hotkey="ctrl+enter, cmd+enter">Cancelar contrato</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
