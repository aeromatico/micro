<?php if ($formModel->exists): ?>
    <?= $this->relationRender('offers') ?>
<?php else: ?>
    <p class="text-muted">Guarda la propiedad para agregar ofertas.</p>
<?php endif ?>
