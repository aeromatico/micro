<?php
$leads = $formModel->exists ? \Aero\Realestate\Models\Lead::where('client_id', $formModel->id)->with('property')->orderByDesc('id')->limit(20)->get() : collect();
$visits = $formModel->exists ? \Aero\Realestate\Models\Visit::where('client_id', $formModel->id)->with('property')->orderByDesc('starts_at')->limit(20)->get() : collect();
$items = $formModel->exists ? \Aero\Realestate\Models\Interaction::where('client_id', $formModel->id)->orderByDesc('id')->limit(50)->get() : collect();
?>
<?php if (!$formModel->exists): ?><p class="text-muted">Guarda el cliente para ver su actividad.</p><?php else: ?>
<p><a class="btn btn-primary btn-sm" href="<?= Backend::url('aero/realestate/leads/create') ?>">Nueva oportunidad</a></p>
<h5>Oportunidades</h5>
<?php if ($leads->isEmpty()): ?><p class="text-muted">Ninguna.</p><?php else: ?>
<ul><?php foreach ($leads as $l): ?><li><a href="<?= Backend::url('aero/realestate/leads/update/' . $l->id) ?>"><?= e($l->property?->title ?? 'Sin propiedad') ?></a> — <?= e($l->stage_name) ?></li><?php endforeach ?></ul>
<?php endif ?>
<h5>Visitas</h5>
<?php if ($visits->isEmpty()): ?><p class="text-muted">Ninguna.</p><?php else: ?>
<ul><?php foreach ($visits as $v): ?><li><a href="<?= Backend::url('aero/realestate/visits/update/' . $v->id) ?>"><?= e($v->starts_at->format('d/m/Y H:i')) ?></a> — <?= e($v->property?->title) ?> (<?= e($v->status_name) ?>)</li><?php endforeach ?></ul>
<?php endif ?>
<h5>Historial de conversaciones y observaciones</h5>
<?php if ($items->isEmpty()): ?><p class="text-muted">Sin registros.</p><?php else: ?>
<table class="table data"><tbody>
<?php foreach ($items as $i): ?><tr><td style="white-space:nowrap"><?= e($i->created_at?->format('d/m/Y H:i')) ?></td><td><?= e($i->type_name) ?></td><td><?= e($i->body) ?></td></tr><?php endforeach ?>
</tbody></table>
<?php endif ?>
<?php endif ?>
