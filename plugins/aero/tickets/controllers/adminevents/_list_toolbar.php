<?php $c = fn ($w) => \Aero\Tickets\Models\Event::where($w)->count(); ?>
<div data-control="toolbar"><span class="text-muted">En revisión: <strong><?= $c(['moderation' => 'pending']) ?></strong> · Suspendidos: <strong><?= $c(['moderation' => 'suspended']) ?></strong> ·
Publicados: <strong><?= \Aero\Tickets\Models\Event::whereIn('status', ['published', 'ongoing'])->count() ?></strong> · Cancelados: <strong><?= $c(['status' => 'cancelled']) ?></strong></span></div>
