<?php if ($openQuestionnaire): $q = $openQuestionnaire; ?>
<?= Form::open(['data-request' => 'onAnswerQuest', 'class' => 'mc-qform']) ?>
    <input type="hidden" name="questionnaire_id" value="<?= (int) $q->id ?>">
    <h5 style="margin-top:10px"><?= e($q->name) ?></h5>
    <?php if ($q->description): ?><p class="text-muted" style="font-size:12px"><?= e($q->description) ?></p><?php endif ?>
    <?php foreach ($q->questions as $qq): ?>
        <fieldset style="margin-bottom:10px">
            <legend style="font-size:13px;font-weight:600;border:0;margin-bottom:2px"><?= e($qq['text']) ?></legend>
            <?php foreach ($qq['options'] as $i => $o): ?>
                <label style="display:block;font-weight:normal"><input type="radio" name="a[<?= e($qq['key']) ?>]" value="<?= (int) $i ?>"> <?= e($o['label']) ?></label>
            <?php endforeach ?>
        </fieldset>
    <?php endforeach ?>
    <button type="submit" class="btn btn-primary btn-sm">Guardar respuestas</button>
<?= Form::close() ?>
<?php endif ?>
