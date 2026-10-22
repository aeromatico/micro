<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Registrar contacto</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Tipo</label><select name="type" class="form-control"><option value="call">Llamada</option><option value="whatsapp">WhatsApp</option><option value="email">Correo</option><option value="note">Nota</option></select></div>
        <div class="form-group"><label>Qué se habló</label><textarea name="body" class="form-control" rows="4" required></textarea></div>
        <div class="form-group"><label>Próximo seguimiento</label><input type="datetime-local" name="next" class="form-control"><p class="help-block">Vacío = en <?= \Aero\Realestate\Models\RealestateSettings::forTenant($m->tenant_id)->followup_days ?> días.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onContact" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Guardar</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
