<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/workflows/runs') ?>">Ejecuciones</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<div class="layout-row"><?= $this->formRenderPreview() ?></div>
<h4 class="m-t">Pasos</h4>
<table class="table data">
    <thead><tr><th>Nodo</th><th>Tipo</th><th>Estado</th><th>ms</th><th>Salida / error</th></tr></thead>
    <tbody>
    <?php foreach ($formModel->steps()->orderBy('id')->get() as $step): ?>
        <tr>
            <td><?= e($step->node_id) ?></td>
            <td><?= e($step->node_type) ?></td>
            <td><?= e($step->status) ?></td>
            <td><?= (int) $step->duration_ms ?></td>
            <td><code><?= e($step->error ?: $step->output) ?></code></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<p><a href="<?= Backend::url('aero/workflows/runs') ?>" class="btn btn-default">Volver</a></p>
