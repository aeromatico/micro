<?php if (!count($answers)): ?>
    <p class="text-muted" style="margin:0 0 8px">Sin cuestionarios respondidos.</p>
<?php else: ?>
<table class="table table-condensed" style="margin-bottom:8px">
<?php foreach ($answers as $a): ?>
    <tr>
        <td><strong><?= e($a->questionnaire_name) ?></strong>
            <div>Puntaje: <strong><?= e(rtrim(rtrim(number_format((float) $a->score, 2, '.', ''), '0'), '.')) ?></strong>
                <?= $a->interpretation ? '· ' . e($a->interpretation) : '<span class="text-muted">· sin interpretación para este puntaje</span>' ?></div>
            <details style="font-size:12px"><summary class="text-muted">Ver respuestas</summary>
                <?php foreach ($a->readable() as $r): ?>
                    <div><?= e($r['question']) ?> — <strong><?= e($r['answer']) ?></strong> (<?= e($r['score']) ?>)</div>
                <?php endforeach ?>
            </details></td>
        <?php if ($editable): ?>
        <td class="text-right"><button type="button" class="btn btn-danger btn-xs" data-request="onRemoveQuest" data-request-data="answer_id: <?= (int) $a->id ?>">Quitar</button></td>
        <?php endif ?>
    </tr>
<?php endforeach ?>
</table>
<?php endif ?>
