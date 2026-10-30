<?php
use Aero\Viral\Classes\ViralStorage;

$samples = $formModel->samples()->orderBy('id')->get();
$canRead = ViralStorage::isConfigured();
?>
<?php if ($formModel->photo_path): ?>
<div class="form-group">
    <label>Foto de perfil (ofrece su perfil a clientes)</label>
    <img src="<?= Backend::url('aero/viral/applications/photo/' . $formModel->id) ?>" alt="Foto de perfil" style="width:140px;height:140px;object-fit:cover;display:block">
</div>
<?php endif ?>
<div class="form-group">
    <label>Muestras (<?= $samples->count() ?>)</label>
    <?php if ($samples->isEmpty()): ?>
        <p class="text-muted">Sin muestras.</p>
    <?php endif ?>
    <?php foreach ($samples as $s): ?>
        <div style="margin-bottom:12px">
            <?php if ($s->kind === 'link'): ?>
                <a href="<?= e($s->url) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($s->url) ?></a>
                <span class="text-muted"> · <?= e($s->platform) ?></span>
            <?php elseif (!$canRead): ?>
                <span class="text-muted">Archivo (<?= e($s->mime) ?>) — configura el almacenamiento para verlo.</span>
            <?php else: $url = ViralStorage::readUrl($s->path, 30); ?>
                <?php if (str_starts_with((string) $s->mime, 'video/')): ?>
                    <video controls preload="metadata" playsinline style="max-width:320px;width:100%;display:block;background:#000" src="<?= e($url) ?>"></video>
                <?php else: ?>
                    <img src="<?= e($url) ?>" alt="Muestra" style="max-width:320px;width:100%;display:block">
                <?php endif ?>
                <span class="text-muted">
                    <?= $s->kind === 'recording' ? 'Grabación' : 'Archivo' ?> · <?= e($s->mime) ?> · <?= number_format($s->size_bytes / 1048576, 1) ?> MB<?= $s->duration_seconds ? ' · ' . (int) $s->duration_seconds . ' s' : '' ?>
                    · <a href="<?= e($url) ?>" target="_blank" rel="noopener">Abrir</a>
                </span>
            <?php endif ?>
        </div>
    <?php endforeach ?>
</div>
