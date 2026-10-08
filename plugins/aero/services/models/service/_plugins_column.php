<?php
use Aero\Services\Classes\PluginCatalog;

$links = (array) $value;
if (!$links) {
    echo '<span class="text-muted">Genérico</span>';
    return;
}

$groups = [];
foreach ($links as $l) {
    $groups[$l['relation'] ?? 'integrates'][] = str_replace('Aero.', '', $l['plugin'] ?? '');
}

foreach (PluginCatalog::RELATIONS as $key => $label) {
    if (!empty($groups[$key])) {
        echo '<div><span class="text-muted">' . e($label) . ':</span> ' . e(implode(', ', $groups[$key])) . '</div>';
    }
}
