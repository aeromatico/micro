<?php if (!empty($previewError)): ?>
    <div class="callout callout-danger"><div class="content"><?= e($previewError) ?></div></div>
<?php elseif ($preview): ?>
    <p class="help-block">Primeras filas desde la fila <?= (int) $preview['from'] ?>. Las letras sirven para referirse a una columna.</p>
    <div style="overflow:auto">
    <table class="table data">
        <thead><tr><th>#</th>
        <?php $max = max(array_map('count', $preview['rows']) ?: [0]); for ($i = 0; $i < $max; $i++): ?>
            <th><?= e(\Aero\Sheets\Classes\SheetsClient::colLetter($i)) ?></th>
        <?php endfor ?></tr></thead>
        <tbody>
        <?php foreach ($preview['rows'] as $n => $row): ?>
            <tr><td><?= (int) $preview['from'] + $n ?></td>
            <?php for ($i = 0; $i < $max; $i++): ?><td><?= e($row[$i] ?? '') ?></td><?php endfor ?></tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <p>La hoja no tiene datos en ese rango.</p>
<?php endif ?>
