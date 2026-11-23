<?= Form::open(['data-request' => 'onBoost']) ?>
<input type="hidden" name="target_id" value="<?= (int) $target->id ?>">
<div class="modal-header">
    <button type="button" class="close" data-dismiss="popup">&times;</button>
    <h4 class="modal-title">Promocionar en <?= e($target->account?->networkLabel()) ?></h4>
</div>
<div class="modal-body">
    <?php if ($adAccounts->isEmpty()): ?>
        <p>Primero conecta tu cuenta publicitaria de Meta en <a href="<?= Backend::url('aero/social/ads') ?>">Anuncios</a>.</p>
    <?php else: ?>
        <div class="form-group">
            <label>Cuenta publicitaria</label>
            <select name="ad_account_id" class="form-control">
                <?php foreach ($adAccounts as $a): ?>
                    <option value="<?= (int) $a->id ?>"><?= e($a->displayName()) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="row">
            <div class="col-sm-6 form-group">
                <label>Presupuesto diario</label>
                <input type="number" name="daily_budget" class="form-control" step="0.01" min="0.01" required placeholder="Ej.: 5">
                <p class="help-block">En la moneda de la cuenta publicitaria.</p>
            </div>
            <div class="col-sm-6 form-group">
                <label>Duración (días)</label>
                <input type="number" name="days" class="form-control" min="1" max="90" value="7" required>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-4 form-group">
                <label>País</label>
                <input type="text" name="country" class="form-control" value="BO" maxlength="2" required>
            </div>
            <div class="col-sm-4 form-group">
                <label>Edad desde</label>
                <input type="number" name="age_min" class="form-control" value="18" min="18" max="65">
            </div>
            <div class="col-sm-4 form-group">
                <label>Edad hasta</label>
                <input type="number" name="age_max" class="form-control" value="65" min="18" max="65">
            </div>
        </div>
        <p class="help-block">Se crea una campaña de interacción que empieza en unos minutos (tras la revisión de Meta). El gasto lo cobra Meta a tu medio de pago; puedes pausarla cuando quieras desde «Anuncios».</p>
    <?php endif ?>
</div>
<div class="modal-footer">
    <?php if ($adAccounts->isNotEmpty()): ?>
        <button type="submit" class="btn btn-primary" data-load-indicator="Creando…">Promocionar</button>
    <?php endif ?>
    <button type="button" class="btn btn-default" data-dismiss="popup">Cancelar</button>
</div>
<?= Form::close() ?>
