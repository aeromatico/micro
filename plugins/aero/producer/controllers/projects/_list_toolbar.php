<div data-control="toolbar">
    <a href="<?= Backend::url('aero/producer/projects/create') ?>" class="btn btn-primary oc-icon-plus">Nuevo</a>
    <?php if (!empty($available)): ?>
        <button class="btn btn-default oc-icon-download" data-request="onImport" data-request-confirm="¿Importar <?= count($available) ?> video(s) de video-cli?">Importar de video-cli (<?= count($available) ?>)</button>
    <?php endif ?>
</div>
