<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/docs/categories') ?>">Categorías</a></li><li>Ordenar</li></ul>
<?php Block::endPut() ?>
<p class="help-block">Arrastra una categoría dentro de otra para anidarla, o sácala para subirla de nivel.</p>
<?= $this->reorderRender() ?>
