<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Cambiar etapa</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Nueva etapa</label><select name="stage" class="form-control"><?php foreach (\Aero\Realestate\Models\Lead::$stages as $k => $v): if ($k === 'closed_won') continue; ?><option value="<?= $k ?>" <?= $k === $m->stage ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></div>
        <div class="form-group"><label>Nota / motivo</label><textarea name="note" class="form-control" rows="3"></textarea><p class="help-block">Obligatorio si se pierde la oportunidad.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onStage" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Cambiar</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
