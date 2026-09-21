<div class="layout-row"><div class="padded-container" style="max-width:720px">
<h3>Nuevo ticket de soporte</h3>
<?= Form::open(['data-request' => 'onCreate']) ?>
    <div class="form-group"><label>Asunto</label><input type="text" name="subject" class="form-control" required maxlength="255"></div>
    <div class="form-group" style="display:flex;gap:12px">
        <div style="flex:1"><label>Departamento</label>
            <select name="department_id" class="form-control" required>
                <option value="">— elige —</option>
                <?php foreach ($departments as $id => $name): ?><option value="<?= $id ?>"><?= e($name) ?></option><?php endforeach ?>
            </select></div>
        <div style="flex:1"><label>Prioridad</label>
            <select name="priority" class="form-control">
                <option value="low">Baja</option><option value="normal" selected>Normal</option>
                <option value="high">Alta</option><option value="urgent">Urgente</option>
            </select></div>
    </div>
    <div class="form-group"><label>Descripción</label><textarea name="description" rows="8" class="form-control"></textarea></div>
    <button type="submit" class="btn btn-primary" data-load-indicator="Enviando...">Enviar ticket</button>
    <a href="<?= Backend::url('aero/crm/support') ?>" class="btn btn-default">Cancelar</a>
<?= Form::close() ?>
</div></div>
