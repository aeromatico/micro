<?php
    $title = e($this->property('title'));
    $id = e($widgetId);
?>
<div class="report-widget" id="<?= $id ?>">
    <h3><?= $title ?></h3>

    <?php if (!$groups): ?>
        <p class="text-muted">No hay áreas disponibles para tu usuario.</p>
    <?php else: ?>
        <input
            type="search"
            class="form-control"
            placeholder="Buscar sección…"
            autocomplete="off"
            style="margin-bottom:10px"
            oninput="(function(i){var q=i.value.toLowerCase().trim(),r=i.closest('.report-widget');r.querySelectorAll('[data-sd-group]').forEach(function(g){var any=false;g.querySelectorAll('[data-sd-link]').forEach(function(a){var m=!q||a.textContent.toLowerCase().indexOf(q)>-1||g.dataset.sdGroup.indexOf(q)>-1;a.style.display=m?'':'none';if(m)any=true;});g.style.display=any?'':'none';});})(this)"
        >

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px">
            <?php foreach ($groups as $g): ?>
                <div data-sd-group="<?= e(mb_strtolower($g['label'])) ?>"
                     style="border:1px solid #e3e6ea;border-radius:6px;padding:8px 10px;background:#fff">
                    <a data-sd-link href="<?= e($g['url']) ?>"
                       style="display:flex;align-items:center;gap:8px;font-weight:600;text-decoration:none;color:inherit;padding:2px 0">
                        <?php if ($g['svg']): ?>
                            <img src="<?= e(Url::asset($g['svg'])) ?>" alt="" style="width:16px;height:16px">
                        <?php else: ?>
                            <i class="<?= e($g['icon']) ?>"></i>
                        <?php endif ?>
                        <span><?= e($g['label']) ?></span>
                    </a>
                    <?php foreach ($g['subs'] as $s): ?>
                        <a data-sd-link href="<?= e($s['url']) ?>"
                           style="display:block;font-size:12px;padding:2px 0 2px 24px;text-decoration:none;color:#6c757d">
                            <?= e($s['label']) ?>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
