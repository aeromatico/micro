<?php if ($formModel->exists): ?>
    <?= $this->relationRender('documents') ?>
<?php else: ?>
    <p class="text-muted">Guarda el contrato para registrar documentos.</p>
<?php endif ?>
