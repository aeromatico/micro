<?php
use Aero\Sheets\Classes\SourceRegistry;
$candidates = SourceRegistry::candidateFields((string) $model->model_class);
$current = collect((array) $model->fields)->keyBy('key');
?>
<?php if (!$candidates): ?>
    <p class="text-danger">No se pudieron leer los campos: revisa que la clase exista y tenga tabla.</p>
<?php else: ?>
<p class="help-block">Marca qué campos pueden <strong>importarse</strong> (hoja → sistema) y/o <strong>exportarse</strong> (sistema → hoja). Los campos de sistema (id, tenant_id, fechas) solo se exportan; las credenciales no se ofrecen nunca.</p>
<table class="table data">
    <thead><tr><th>Campo</th><th>Tipo</th><th>Etiqueta</th><th>Importar</th><th>Exportar</th></tr></thead>
    <tbody>
    <?php foreach ($candidates as $key => $c): $cur = $current[$key] ?? null; ?>
        <tr>
            <td><code><?= e($key) ?></code></td>
            <td><?= e($c['type']) ?></td>
            <td><input type="text" class="form-control" name="sync_fields[<?= e($key) ?>][label]" value="<?= e($cur['label'] ?? $c['label']) ?>"></td>
            <td><input type="checkbox" name="sync_fields[<?= e($key) ?>][import]" value="1" <?= !empty($cur['import']) ? 'checked' : '' ?> <?= $c['readonly'] ? 'disabled' : '' ?>></td>
            <td><input type="checkbox" name="sync_fields[<?= e($key) ?>][export]" value="1" <?= !empty($cur['export']) ? 'checked' : '' ?>></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
