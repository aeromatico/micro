<?php foreach ($departments as $d): ?>
    <span class="badge" style="background:<?= e($d->color ?: '#64748b') ?>;color:#fff;margin-right:3px"><?= e($d->name) ?></span>
<?php endforeach ?>
<a href="javascript:;"
   data-control="popup"
   data-handler="onLoadDepartments"
   data-request-data="id: <?= (int) $record->id ?>"
   onclick="event.stopPropagation()">
    <?= $departments ? 'Editar' : 'Asignar' ?>
</a>
