<?php if ($formModel->exists): ?>
<p><a class="btn btn-primary btn-sm" href="<?= Backend::url('aero/realestate/documents/create?agreement_id=' . (int) $formModel->id) ?>">Agregar documento</a></p>
<?php $docs = $formModel->documents()->orderByDesc('id')->get(); ?>
<?php if ($docs->isEmpty()): ?><p class="text-muted">Sin documentos.</p><?php else: ?>
<table class="table data"><tbody><?php foreach ($docs as $d): ?>
<tr><td><a href="<?= Backend::url('aero/realestate/documents/update/' . $d->id) ?>"><?= e($d->name) ?></a></td><td><?= e($d->type_name) ?></td><td><?= e($d->status_name) ?></td></tr>
<?php endforeach ?></tbody></table><?php endif ?>
<?php else: ?><p class="text-muted">Guarda el contrato para adjuntar documentos.</p><?php endif ?>
