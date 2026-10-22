<?php if (!$formModel->exists): ?><p class="text-muted">Guarda el registro para gestionar sus documentos.</p><?php else: ?>
<?php $docs = \Aero\Realestate\Models\Document::where('property_id', $formModel->id)->orderByDesc('id')->get(); ?>
<p><a class="btn btn-primary btn-sm" href="<?= Backend::url('aero/realestate/documents/create?property_id=' . (int) $formModel->id) ?>">Agregar documento</a>
<a class="btn btn-default btn-sm" href="<?= Backend::url('aero/realestate/agreements/create?property_id=' . (int) $formModel->id) ?>">Generar contrato</a></p>
<?php if ($docs->isEmpty()): ?><p class="text-muted">Sin documentos.</p><?php else: ?>
<table class="table data"><tbody><?php foreach ($docs as $d): ?>
<tr><td><a href="<?= Backend::url('aero/realestate/documents/update/' . $d->id) ?>"><?= e($d->name) ?></a></td><td><?= e($d->type_name) ?></td><td><?= e($d->status_name) ?></td><td><?= $d->expires_on ? 'vence ' . e($d->expires_on->format('d/m/Y')) : '' ?></td></tr>
<?php endforeach ?></tbody></table><?php endif ?>
<?php endif ?>
