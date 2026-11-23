<?= Form::open(['data-request' => 'onBudget']) ?>
<input type="hidden" name="campaign_id" value="<?= (int) $campaign->id ?>">
<div class="modal-header">
    <button type="button" class="close" data-dismiss="popup">&times;</button>
    <h4 class="modal-title">Presupuesto: <?= e($campaign->name) ?></h4>
</div>
<div class="modal-body">
    <?php if (!$owner): ?>
        <p>Esta campaña reparte el presupuesto entre varios conjuntos de anuncios; cámbialo desde el Administrador de anuncios de Meta.</p>
    <?php else: ?>
        <div class="form-group">
            <label><?= $owner->daily_budget ? 'Presupuesto diario' : 'Presupuesto total' ?> (<?= e($account->currency) ?>)</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required
                   value="<?= e($account->toMajor($owner->daily_budget ?: $owner->lifetime_budget)) ?>">
            <p class="help-block">Meta aplica el cambio en minutos. Puede pedir un mínimo según la moneda.</p>
        </div>
    <?php endif ?>
</div>
<div class="modal-footer">
    <?php if ($owner): ?><button type="submit" class="btn btn-primary" data-load-indicator="Guardando…">Guardar</button><?php endif ?>
    <button type="button" class="btn btn-default" data-dismiss="popup">Cancelar</button>
</div>
<?= Form::close() ?>
