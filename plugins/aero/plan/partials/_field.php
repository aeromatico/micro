<?php
$k = $f['k'];
$id = 'f-' . $k;
$v = $answers[$k] ?? null;
$ro = !$editableClient;
$label = e($f['l']) . (!empty($f['req']) ? ' <span class="aplan-req" title="Obligatorio">*</span>' : '');
$hint = !empty($f['h']) ? '<div class="aplan-hint">' . e($f['h']) . '</div>' : '';
?>
<?php if (in_array($f['t'], ['text', 'url', 'date'], true)): ?>
    <div class="aplan-field"><label for="<?= $id ?>"><?= $label ?></label><?= $hint ?>
        <input id="<?= $id ?>" name="a[<?= e($k) ?>]" type="<?= $f['t'] === 'url' ? 'text' : $f['t'] ?>" class="form-control" value="<?= e((string) $v) ?>" <?= $f['t'] === 'url' ? 'placeholder="https://" inputmode="url"' : '' ?> <?= $f['t'] === 'text' ? 'maxlength="300"' : '' ?> <?= $ro ? 'disabled' : '' ?>>
    </div>
<?php elseif ($f['t'] === 'area'): ?>
    <div class="aplan-field"><label for="<?= $id ?>"><?= $label ?></label><?= $hint ?>
        <textarea id="<?= $id ?>" name="a[<?= e($k) ?>]" class="form-control" rows="3" maxlength="4000" <?= $ro ? 'disabled' : '' ?>><?= e((string) $v) ?></textarea>
    </div>
<?php elseif ($f['t'] === 'select'): ?>
    <div class="aplan-field"><label for="<?= $id ?>"><?= $label ?></label>
        <select id="<?= $id ?>" name="a[<?= e($k) ?>]" class="form-control" <?= $k === 'rubro' ? 'data-plan-rubro' : '' ?> <?= $ro ? 'disabled' : '' ?>>
            <option value="">Elige una opción</option>
            <?php foreach ($f['o'] as $o): ?><option value="<?= e($o) ?>" <?= $v === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach ?>
        </select>
    </div>
<?php elseif (in_array($f['t'], ['multi', 'tasks'], true)): ?>
    <div class="aplan-field" role="group" aria-labelledby="<?= $id ?>-l"><span class="aplan-lbl" id="<?= $id ?>-l"><?= $label ?></span><?= $hint ?>
        <div class="aplan-opts <?= $f['t'] === 'tasks' ? 'col' : '' ?>">
            <?php foreach ($f['o'] as $o): ?>
                <label class="aplan-opt"><input type="checkbox" name="a[<?= e($k) ?>][]" value="<?= e($o) ?>" <?= in_array($o, (array) $v, true) ? 'checked' : '' ?> <?= $ro ? 'disabled' : '' ?>> <?= e($o) ?></label>
            <?php endforeach ?>
        </div>
    </div>
<?php elseif ($f['t'] === 'confirm'): $ready = trim((string) ($answers[$f['needs']] ?? '')) !== ''; ?>
    <div class="aplan-field">
        <label class="aplan-opt" for="<?= $id ?>"><input id="<?= $id ?>" type="checkbox" name="a[<?= e($k) ?>]" value="1" <?= $v ? 'checked' : '' ?> <?= $ro || !$ready ? 'disabled' : '' ?>> <?= $label ?></label>
        <?php if (!$ready): ?><div class="aplan-hint">Se habilita cuando el equipo publique el alcance.</div><?php endif ?>
    </div>
<?php elseif ($f['t'] === 'team'): $txt = trim((string) $v); ?>
    <div class="aplan-team">
        <span class="aplan-who">Redacta el equipo</span><b><?= e($f['l']) ?></b>
        <?php if ($editableTeam): ?>
            <textarea id="<?= $id ?>" name="t[<?= e($k) ?>]" class="form-control" rows="4" maxlength="4000" placeholder="Redacta el alcance para el dueño"><?= e($txt) ?></textarea>
        <?php elseif ($txt !== ''): ?>
            <div><?= nl2br(e($txt)) ?></div>
        <?php else: ?>
            <div class="aplan-hint"><?= e($f['ph'] ?? '') ?></div>
        <?php endif ?>
    </div>
<?php elseif ($f['t'] === 'tracker'): $tr = (array) $v; ?>
    <div class="aplan-team">
        <span class="aplan-who">Actualiza el equipo</span><b><?= e($f['l']) ?></b>
        <?php foreach ($f['items'] as $i => $item): $s = $tr[$i] ?? 'pending'; ?>
            <div class="aplan-crow"><span><?= e($item) ?></span>
                <?php if ($editableTeam): ?>
                    <select name="t[<?= e($k) ?>][<?= $i ?>]" class="form-control aplan-mini">
                        <?php foreach (\Aero\Plan\Classes\Stages::TRACKER_STATES as $code => $lbl): ?><option value="<?= $code ?>" <?= $s === $code ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach ?>
                    </select>
                <?php else: ?>
                    <span class="aplan-pill <?= $s ?>"><?= e(\Aero\Plan\Classes\Stages::TRACKER_STATES[$s] ?? $s) ?></span>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
