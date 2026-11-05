<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/guests') ?>">Invitados</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <?php if ($formModel->isRevoked()): ?>
            <button type="button" class="btn btn-success" data-request="onRestore" data-request-data="id: <?= (int) $formModel->id ?>">Restaurar invitación</button>
        <?php else: ?>
            <button type="button" class="btn btn-warning" data-request="onRevoke" data-request-data="id: <?= (int) $formModel->id ?>"
                    data-request-confirm="¿Revocar esta invitación? Sus entradas sin usar se anulan.">Revocar invitación</button>
        <?php endif ?>
        <?php if (!$formModel->tickets()->exists()): ?>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este invitado?">Eliminar</button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/guests') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
