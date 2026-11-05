<?php $c = fn ($s) => \Aero\Tickets\Models\Organizer::where('status', $s)->count(); ?>
<div data-control="toolbar"><span class="text-muted">Por aprobar: <strong><?= $c('pending') ?></strong> · Aprobados: <strong><?= $c('approved') ?></strong> · Suspendidos: <strong><?= $c('suspended') ?></strong> · Rechazados: <strong><?= $c('rejected') ?></strong>
 — Los organizadores se registran al usar el módulo; filtra por «Por aprobar» para ver las solicitudes.</span></div>
