<?php $colors = ['pending' => '#f59e0b', 'fulfilled' => '#22c55e', 'cancelled' => '#dc3545']; ?>
<span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;background:<?= $colors[$record->status] ?? '#999' ?>22;color:<?= $colors[$record->status] ?? '#999' ?>">
    <?= e($record->status_label) ?>
</span>
