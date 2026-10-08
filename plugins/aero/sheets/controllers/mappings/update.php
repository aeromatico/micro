<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/sheets/mappings') ?>">Mapeos</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$connected): ?>
    <div class="callout callout-warning no-subheader"><div class="header"><h3>Falta conectar Google Sheets</h3></div>
        <div class="content"><p>Para leer o escribir hojas necesitas dar permiso a tu cuenta de Google.</p>
        <a href="<?= e($connectUrl) ?>" class="btn btn-primary">Conectar Google Sheets</a></div></div>
<?php endif ?>

<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>

    <div class="form-buttons"><div class="loading-indicator-container">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+s, cmd+s" data-load-indicator="Guardando…" class="btn btn-primary">Guardar</button>
        <button type="submit" data-request="onSave" data-request-data="close:1" data-load-indicator="Guardando…" class="btn btn-default">Guardar y cerrar</button>
        <span class="btn-text" style="margin-left:12px">Guarda antes de usar estas acciones →</span>
        <button type="button" class="btn btn-default" data-request="onPreview" data-load-indicator="Leyendo la hoja…">Ver hoja</button>
        <button type="button" class="btn btn-default" data-request="onAutoMap" data-request-confirm="Se reemplazarán las columnas actuales. ¿Continuar?" data-load-indicator="Emparejando…">Auto-mapear</button>
        <button type="button" class="btn btn-default" data-request="onRun" data-request-data="dry:1" data-load-indicator="Probando…">Probar sin guardar</button>
        <button type="button" class="btn btn-success" data-request="onRun" data-request-confirm="Se ejecutará la sincronización de verdad. ¿Continuar?" data-load-indicator="Sincronizando…">Ejecutar ahora</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este mapeo?">Eliminar</button>
    </div></div>
<?= Form::close() ?>

<div id="sheets-preview" style="margin-top:16px"></div>
