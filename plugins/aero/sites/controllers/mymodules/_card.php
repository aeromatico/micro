<?php /** @var array $item */ ?>
<label class="mm-card<?= $item['locked'] ? ' is-locked' : '' ?><?= $item['enabled'] ? ' is-on' : '' ?>"
       data-code="<?= e($item['code']) ?>"
       data-name="<?= e(mb_strtolower($item['name'] . ' ' . $item['desc'])) ?>"
       data-requires="<?= e(implode(',', $item['requires'])) ?>">
    <span class="mm-icon"><i class="<?= e($item['icon']) ?>"></i></span>
    <span class="mm-body">
        <span class="mm-title">
            <?= e($item['name']) ?>
            <?php if ($item['locked']): ?>
                <span class="mm-badge mm-badge-lock">🔒 <?= $item['upgrade'] ? 'Plan ' . e($item['upgrade']) : 'Otro plan' ?></span>
            <?php elseif ($item['essential']): ?>
                <span class="mm-badge">Recomendado</span>
            <?php elseif ($item['suggested']): ?>
                <span class="mm-badge mm-badge-niche">Para tu rubro</span>
            <?php endif ?>
        </span>
        <span class="mm-desc"><?= e($item['desc']) ?></span>
        <?php if ($item['locked']): ?>
            <a class="mm-upgrade" href="<?= Backend::url('aero/sites/upgrade?from=' . urlencode($item['code'])) ?>">Mejorar plan →</a>
        <?php endif ?>
    </span>
    <span class="mm-switch">
        <input type="checkbox" value="<?= e($item['code']) ?>"
               <?= $item['enabled'] ? 'checked' : '' ?> <?= $item['locked'] ? 'disabled' : '' ?>
               aria-label="<?= e($item['name']) ?>">
        <span class="mm-slider"></span>
    </span>
</label>
