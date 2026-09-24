<div class="layout-row">
<?= Form::open(['class' => 'layout', 'files' => true]) ?>

    <?php if ($driver === 'simulated'): ?>
        <p class="flash-message static warning">El proveedor está en modo <strong>Simulado</strong>: no se enviará ningún SMS real.</p>
    <?php endif ?>

    <?php if ($isAdmin): ?>
    <div class="form-group span-left">
        <label>Atribuir a</label>
        <select name="tenant_id" class="form-control custom-select">
            <option value="">Plataforma (no se cobra)</option>
            <?php foreach ($tenants as $id => $name): ?>
                <option value="<?= $id ?>"><?= e($name) ?></option>
            <?php endforeach ?>
        </select>
        <p class="help-block">El tenant elegido paga los créditos de este envío y lo verá en su consumo.</p>
    </div>
    <?php else: ?>
    <div class="form-group span-left">
        <label>Tarifa</label>
        <p class="form-control-static">
            <?= $this->makePartial('$/aero/credits/partials/_credit_cost_hint.htm', [
                'actionCode' => \Aero\Sms\Classes\Billing::ACTION,
                'tenantId'   => $tenantId,
            ]) ?>
            <br><small class="text-muted">por segmento (un SMS puede usar varios)</small>
        </p>
    </div>
    <?php endif ?>

    <div class="form-group span-right">
        <label>Nombre del lote <small>(opcional)</small></label>
        <input type="text" name="name" class="form-control" placeholder="Promo de septiembre">
    </div>

    <div class="form-group span-full">
        <label>Destinatarios</label>
        <textarea name="recipients" rows="6" class="form-control" placeholder="71234567&#10;72345678&#10;&#10;o con variables:&#10;telefono,nombre&#10;71234567,Ana"></textarea>
        <p class="help-block">
            Uno por línea. Sin <code>+</code> se asume el prefijo del país por defecto. Si la primera línea es una cabecera,
            sus columnas son las variables (<code>{{nombre}}</code>). Máximo <?= $maxBatch ?>.
        </p>
    </div>

    <div class="form-group span-full">
        <label>o subir un CSV</label>
        <input type="file" name="csv" accept=".csv,.txt" class="form-control">
    </div>

    <div class="form-group span-left">
        <label>Plantilla</label>
        <select name="template" class="form-control custom-select">
            <option value="">— Escribir mensaje —</option>
            <?php foreach ($templates as $t): ?>
                <option value="<?= e($t->slug) ?>"><?= e($t->name) ?></option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="form-group span-right">
        <label>Programar <small>(opcional)</small></label>
        <input type="datetime-local" name="scheduled_at" class="form-control">
    </div>

    <div class="form-group span-full">
        <label>Mensaje</label>
        <textarea name="body" rows="4" class="form-control" placeholder="Hola {{nombre}}, ..."></textarea>
        <p class="help-block">Se ignora si eliges una plantilla. Las tildes en mayúscula, la «ó» o los emojis obligan a UCS-2 (70 caracteres por segmento).</p>
    </div>

    <div id="quote"></div>

    <div class="form-buttons">
        <button type="button" class="btn btn-default" data-request="onQuote">Cotizar</button>
        <button type="button" class="btn btn-primary" data-request="onSend"
            data-request-confirm="¿Enviar ahora? Se cobrarán los créditos al aceptar.">Enviar</button>
    </div>

<?= Form::close() ?>
</div>
