<div class="callout callout-info" style="margin-bottom:15px"><div class="content"><p>
    <strong><?= e($m->property?->title) ?></strong> · <?= e($m->modality_name) ?> · cliente <?= e($m->client?->name) ?> · agente <?= e($m->agent?->name ?? '—') ?><br>
    Base <?= number_format((float) $m->base_amount, 2, ',', '.') ?> <?= e($m->currency) ?> · regla: <?= e($m->rule_text) ?> ·
    comisión <strong><?= number_format((float) $m->commission_amount, 2, ',', '.') ?></strong> ·
    agente (<?= rtrim(rtrim(number_format((float) $m->agent_share_percent, 2, ',', ''), '0'), ',') ?> %) <strong><?= number_format((float) $m->agent_amount, 2, ',', '.') ?></strong>
</p></div></div>
