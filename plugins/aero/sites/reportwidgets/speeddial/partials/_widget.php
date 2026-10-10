<?php
    $title = e($this->property('title'));
    $id = e($widgetId);
?>
<style>
    #<?= $id ?> { color: inherit; }
    #<?= $id ?> .sd-search { width: 100%; margin-bottom: 10px; background: transparent; color: inherit;
        border: 1px solid color-mix(in srgb, currentColor 18%, transparent); border-radius: 6px; padding: 6px 10px; }
    #<?= $id ?> .sd-scroll { max-height: 420px; overflow-y: auto; padding-right: 4px; scrollbar-width: thin; }
    #<?= $id ?> .sd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 8px; align-items: start; }
    #<?= $id ?> .sd-card { border: 1px solid color-mix(in srgb, currentColor 14%, transparent); border-radius: 8px;
        background: color-mix(in srgb, currentColor 4%, transparent); padding: 6px; }
    #<?= $id ?> .sd-card a { color: inherit; text-decoration: none; display: flex; align-items: center; gap: 8px;
        border-radius: 5px; }
    #<?= $id ?> .sd-main { font-weight: 600; padding: 6px; }
    #<?= $id ?> .sd-main:hover, #<?= $id ?> .sd-sub:hover { background: color-mix(in srgb, currentColor 10%, transparent); }
    #<?= $id ?> .sd-ico { flex: 0 0 18px; width: 18px; height: 18px; font-size: 16px; line-height: 18px;
        text-align: center; display: inline-block; }
    #<?= $id ?> .sd-ico.is-mask { background: currentColor; -webkit-mask: var(--sd-icon) center/contain no-repeat;
        mask: var(--sd-icon) center/contain no-repeat; }
    #<?= $id ?> .sd-subs { max-height: 7.5em; overflow-y: auto; margin-top: 2px; scrollbar-width: thin; }
    #<?= $id ?> .sd-sub { font-size: 12px; opacity: .75; padding: 3px 6px 3px 32px; }
    #<?= $id ?> .sd-sub:hover { opacity: 1; }
</style>
<div class="report-widget" id="<?= $id ?>">
    <h3><?= $title ?></h3>

    <?php if (!$groups): ?>
        <p class="text-muted">No hay áreas disponibles para tu usuario.</p>
    <?php else: ?>
        <input
            type="search"
            class="sd-search"
            placeholder="Buscar sección…"
            autocomplete="off"
            oninput="(function(i){var q=i.value.toLowerCase().trim(),r=i.closest('.report-widget');r.querySelectorAll('[data-sd-group]').forEach(function(g){var any=false;g.querySelectorAll('[data-sd-link]').forEach(function(a){var m=!q||a.textContent.toLowerCase().indexOf(q)>-1||g.dataset.sdGroup.indexOf(q)>-1;a.style.display=m?'':'none';if(m)any=true;});g.style.display=any?'':'none';});})(this)"
        >

        <div class="sd-scroll">
            <div class="sd-grid">
                <?php foreach ($groups as $g): ?>
                    <div class="sd-card" data-sd-group="<?= e(mb_strtolower($g['label'])) ?>">
                        <a class="sd-main" data-sd-link href="<?= e($g['url']) ?>">
                            <?php if ($g['svg']): ?>
                                <span class="sd-ico is-mask" style="--sd-icon:url('<?= e(Url::asset($g['svg'])) ?>')"></span>
                            <?php else: ?>
                                <i class="sd-ico <?= e($g['icon']) ?>"></i>
                            <?php endif ?>
                            <span><?= e($g['label']) ?></span>
                        </a>
                        <?php if ($g['subs']): ?>
                            <div class="sd-subs">
                                <?php foreach ($g['subs'] as $s): ?>
                                    <a class="sd-sub" data-sd-link href="<?= e($s['url']) ?>"><?= e($s['label']) ?></a>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
</div>
