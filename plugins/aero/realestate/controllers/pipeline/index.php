<?php $url = fn ($l) => Backend::url('aero/realestate/leads/update/' . $l->id); ?>
<p class="text-muted" style="margin-bottom:10px">Arrastra una oportunidad a otra columna para cambiarla de etapa. Para <strong>cerrar la operación</strong> abre la oportunidad: ahí eliges la oferta y se calcula la comisión.</p>
<div id="pipeline-wrap">
    <div id="pipeline-board"><?= $this->makePartial('board', ['board' => $board]) ?></div>
</div>
<script>
(function () {
    var wrap = document.getElementById('pipeline-wrap'), dragging = null;
    var WON = 'Para cerrar la operación abre la oportunidad y usa «Cerrar operación»: ahí se elige la oferta y se calcula la comisión.';

    function move(card, stage) {
        var from = card.dataset.stageOf;
        if (!stage || stage === from) return;
        if (stage === 'closed_won') { alert(WON); return; }
        var note = null;
        if (stage === 'closed_lost') {
            note = prompt('¿Por qué se perdió la oportunidad?');
            if (note === null || !note.trim()) return;
        }
        var col = wrap.querySelector('[data-stage="' + stage + '"] [data-cards]');
        if (col) col.insertBefore(card, col.firstChild);   // se mueve de inmediato; el servidor devuelve el tablero real
        oc.ajax('onMove', { data: { id: card.dataset.lead, stage: stage, note: note } })
            .catch(function () { window.location.reload(); });   // si no se pudo, vuelve a como estaba (el mensaje de error ya se mostró)
    }

    wrap.addEventListener('dragstart', function (e) {
        var card = e.target.closest && e.target.closest('[data-lead]');
        if (!card || card.getAttribute('draggable') !== 'true') return;
        dragging = card;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.lead);
        setTimeout(function () { card.style.opacity = '.45'; }, 0);
    });
    wrap.addEventListener('dragend', function () {
        if (dragging) dragging.style.opacity = '';
        dragging = null;
        wrap.querySelectorAll('[data-stage]').forEach(function (c) { c.style.outline = ''; });
    });
    wrap.addEventListener('dragover', function (e) {
        var col = e.target.closest && e.target.closest('[data-stage]');
        if (!col || !dragging) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        wrap.querySelectorAll('[data-stage]').forEach(function (c) { c.style.outline = c === col ? '2px dashed var(--bs-primary)' : ''; });
    });
    wrap.addEventListener('drop', function (e) {
        var col = e.target.closest && e.target.closest('[data-stage]');
        if (!col || !dragging) return;
        e.preventDefault();
        var card = dragging;
        card.style.opacity = '';
        wrap.querySelectorAll('[data-stage]').forEach(function (c) { c.style.outline = ''; });
        move(card, col.dataset.stage);
    });
})();
</script>
<h4 style="margin-top:25px">Próximas visitas</h4>
<?php if ($visits->isEmpty()): ?><p class="text-muted">Sin visitas programadas.</p><?php else: ?>
<table class="table data"><thead><tr><th>Fecha</th><th>Propiedad</th><th>Cliente</th><th>Agente</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($visits as $v): ?>
    <tr><td><a href="<?= Backend::url('aero/realestate/visits/update/' . $v->id) ?>"><?= e($v->starts_at->format('d/m/Y H:i')) ?></a></td><td><?= e($v->property?->title) ?></td><td><?= e($v->client?->name) ?></td><td><?= e($v->agent?->name) ?></td><td><?= e($v->status_name) ?></td></tr>
<?php endforeach ?>
</tbody></table>
<?php endif ?>
