<?php $notes = $model->notes()->orderByDesc('id')->get(); ?>
<div class="form-group" style="margin-top:20px">
    <p class="text-muted">Información confidencial: solo la dirección pastoral y el responsable asignado pueden verla.</p>
    <h4>Acompañamiento (<?= $notes->count() ?>)</h4>
    <?php foreach ($notes as $n): ?>
        <div style="border-left:3px solid #ccc;padding:4px 10px;margin:8px 0">
            <small class="text-muted"><?= e($n->created_at->format('d/m/Y H:i')) ?></small><br><?= nl2br(e($n->note)) ?>
        </div>
    <?php endforeach ?>
    <?= Form::open() ?>
    <div class="form-inline" style="margin-top:10px">
        <input type="text" name="note" class="form-control" placeholder="Nota de acompañamiento" style="min-width:380px">
        <button type="button" class="btn btn-primary" data-request="onAddNote">Agregar nota</button>
    </div>
    <?= Form::close() ?>
</div>
