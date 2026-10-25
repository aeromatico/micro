<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title><?= e($title) ?></title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h1{font-size:15px;margin:0 0 2px}.meta{color:#777;font-size:8px;margin-bottom:10px}
table{width:100%;border-collapse:collapse}th{background:#eef0f3;text-align:left;font-size:8.5px}
th,td{border:1px solid #ccd0d6;padding:3px 5px;vertical-align:top;word-wrap:break-word}
tr:nth-child(even) td{background:#f8f9fa}
</style></head><body>
<h1><?= e($title) ?></h1>
<div class="meta"><?= e($date) ?> · <?= count($rows) ?> registros</div>
<table><thead><tr><?php foreach ($header as $h): ?><th><?= e($h) ?></th><?php endforeach ?></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= e($cell) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table>
</body></html>
