<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/clinica/start') ?>">Primeros pasos</a></li></ul>
<?php Block::endPut() ?>
<?php if (!$tenant): ?>
    <p class="flash-message static warning">Elija un negocio (sitio) para ver sus primeros pasos.</p>
<?php else: ?>
<style>
    .cl-step{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;margin-bottom:8px;border:1px solid rgba(128,128,128,.3);border-radius:4px;background:rgba(128,128,128,.06)}
    .cl-step .n{flex:0 0 26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;background:rgba(128,128,128,.3)}
    .cl-step.done .n{background:#2e9e5b;color:#fff}.cl-step.done{opacity:.8}
    .cl-step h4{margin:0 0 3px;font-size:15px}.cl-step p{margin:0;font-size:13px;opacity:.85}
    .cl-step .go{margin-left:auto;white-space:nowrap}
</style>
<div style="max-width:820px">
    <p style="font-size:14px">Cuatro pasos y ya puede atender. Todo lo demás es opcional: puede escribirlo a mano en la consulta.</p>
    <h5 style="text-transform:uppercase;font-size:12px;letter-spacing:.04em;opacity:.7">Lo necesario</h5>
    <?php foreach ($required as $i => $s): ?>
        <div class="cl-step <?= $s['done'] ? 'done' : '' ?>">
            <div class="n"><?= $s['done'] ? '✓' : $i + 1 ?></div>
            <div><h4><?= e($s['title']) ?></h4><p><?= e($s['why']) ?></p></div>
            <a class="btn btn-<?= $s['done'] ? 'default' : 'primary' ?> btn-sm go" href="<?= e($s['url']) ?>"><?= $s['done'] ? 'Revisar' : e($s['cta']) ?></a>
        </div>
    <?php endforeach ?>
    <h5 style="margin-top:22px;text-transform:uppercase;font-size:12px;letter-spacing:.04em;opacity:.7">Opcional, cuando lo necesite</h5>
    <?php foreach ($optional as $s): ?>
        <div class="cl-step <?= $s['done'] ? 'done' : '' ?>">
            <div class="n"><?= $s['done'] ? '✓' : '·' ?></div>
            <div><h4><?= e($s['title']) ?></h4><p><?= e($s['why']) ?></p></div>
            <a class="btn btn-default btn-sm go" href="<?= e($s['url']) ?>">Abrir</a>
        </div>
    <?php endforeach ?>
</div>
<?php endif ?>
