<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Reprogramar visita</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Nueva fecha y hora</label><input type="datetime-local" name="starts_at" class="form-control" required></div>
        <div class="form-group"><label>Motivo (opcional)</label><input type="text" name="reason" class="form-control"></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onReschedule" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Reprogramar</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
