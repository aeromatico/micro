<?php
$links = (array) $value;
echo $links ? e(implode(', ', array_map(fn ($l) => $l['plugin'] ?? '', $links))) : '<span class="text-muted">Genérico</span>';
