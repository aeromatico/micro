<?php if ($formModel->exists): ?>
    <?= $this->relationRender('payments') ?>
<?php else: ?>
    <p class="text-muted">Guarda el contrato para registrar entregas y devoluciones.</p>
<?php endif ?>
