<form data-request="onSaveDepartments" data-request-success="$('.control-popup').popup('hide')">
    <input type="hidden" name="id" value="<?= (int) $tenantUser->id ?>">
    <div class="modal-header">
        <h4 class="modal-title">Departamentos de <?= e($tenantUser->user_email) ?></h4>
        <button type="button" class="btn-close" data-dismiss="popup"></button>
    </div>
    <div class="modal-body">
        <?php if ($departments->isEmpty()): ?>
            <p class="text-muted">Todavía no hay departamentos. Creá uno en CRM → Departamentos.</p>
        <?php else: foreach ($departments as $d): ?>
            <div class="checkbox custom-checkbox">
                <input type="checkbox" name="departments[]" id="dept-<?= $d->id ?>" value="<?= $d->id ?>" <?= in_array($d->id, $selected) ? 'checked' : '' ?>>
                <label for="dept-<?= $d->id ?>"><?= e($d->name) ?></label>
            </div>
        <?php endforeach; endif ?>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="popup">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
</form>
