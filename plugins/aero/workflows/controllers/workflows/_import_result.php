<?php foreach ($done as $row): $w = $row['workflow']; ?>
    <div class="callout callout-success no-subheader" style="margin-bottom:10px">
        <div class="header">
            <i class="icon-check"></i>
            <h3><a href="<?= Backend::url('aero/workflows/workflows/update/' . $w->id) ?>"><?= e($w->name) ?></a></h3>
            <p>Borrador desactivado · código <code><?= e($w->slug) ?></code></p>
            <?php if ($row['reconnect']): ?>
                <p><strong>Antes de publicar, reconecta:</strong></p>
                <ul>
                    <?php foreach ($row['reconnect'] as $r): ?>
                        <li><?= e(($r['where'] ?? 'Nodo') . ': ' . ($r['what'] ?? '')) ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
            <?php if ($row['warnings']): ?>
                <p><strong>Revisa (el editor no lo cierra solo):</strong></p>
                <ul>
                    <?php foreach (array_slice($row['warnings'], 0, 6) as $m): ?>
                        <li><?= e($m) ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
<?php endforeach ?>

<?php foreach ($failed as $message): ?>
    <div class="callout callout-danger no-subheader" style="margin-bottom:10px">
        <div class="header"><i class="icon-warning"></i><h3>No se importó</h3><p><?= e($message) ?></p></div>
    </div>
<?php endforeach ?>
