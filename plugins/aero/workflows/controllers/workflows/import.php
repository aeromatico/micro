<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/workflows/workflows') ?>">Workflows</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>

<div class="callout callout-info no-subheader" style="margin-bottom:15px">
    <div class="header">
        <i class="icon-info"></i>
        <h3>Cómo funciona</h3>
        <p>
            Sube un archivo <code>.json</code> exportado desde otro workflow (o pega su contenido). Cada workflow entra como
            <strong>borrador desactivado</strong>: no se ejecuta solo hasta que lo revises, reconectes lo que se te indique
            (cuentas, Connectors, credenciales…) y lo publiques. Nada del archivo se ejecuta al importar.
        </p>
    </div>
</div>

<?= Form::open(['class' => 'layout', 'data-request' => 'onImport', 'data-request-files' => 'true', 'data-request-flash' => 'true', 'enctype' => 'multipart/form-data']) ?>
    <div class="form-group">
        <label for="import-file">1. Archivo</label>
        <input type="file" id="import-file" name="file" accept=".json,application/json" class="form-control">
    </div>
    <div class="form-group">
        <label for="import-json">2. …o pega el contenido</label>
        <textarea id="import-json" name="json" class="form-control" rows="8" placeholder='{"format":"aero.workflow", …}'></textarea>
        <p class="help-block">Si subes un archivo, se usa el archivo. Máximo 1 MB y 20 workflows por archivo.</p>
    </div>
    <div class="form-buttons">
        <button type="submit" class="btn btn-primary oc-icon-upload" data-load-indicator="Importando…">Importar</button>
        <a href="<?= Backend::url('aero/workflows/workflows') ?>" class="btn btn-default">Volver a la lista</a>
    </div>
<?= Form::close() ?>

<div id="import-result" style="margin-top:20px"></div>
