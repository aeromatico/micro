<?php $feed = $formModel; $events = $feed->events()->limit(100)->get(); ?>
<div class="form-group span-full">
    <label>Línea de tiempo</label>
    <?php if ($events->isEmpty()): ?>
        <p class="text-muted">Sin eventos todavía.</p>
    <?php else: ?>
    <table class="table data">
        <tbody>
        <?php foreach ($events as $ev): $d = (array) $ev->data; ?>
            <tr>
                <td style="white-space:nowrap"><?= e($ev->occurred_at->format('d/m H:i:s')) ?></td>
                <td><strong><?= e($ev->type_label) ?></strong></td>
                <td>
                    <?php if ($ev->type === 'phase_changed'): ?>
                        <?= e(\Aero\Tracking\Models\Feed::PHASES[$d['from'] ?? ''] ?? $d['from'] ?? '') ?> → <?= e(\Aero\Tracking\Models\Feed::PHASES[$d['to'] ?? ''] ?? $d['to'] ?? '') ?>
                    <?php elseif ($ev->type === 'eta_changed' || $ev->type === 'delay_changed'): ?>
                        <?= e($d['from'] ?? '—') ?> → <?= e($d['to'] ?? '—') ?>
                    <?php elseif ($ev->type === 'message_changed'): ?>
                        <?= e($d['text'] ?? '') ?>
                    <?php elseif (!empty($d['reason'])): ?>
                        <?= e($d['reason']) ?>
                    <?php elseif ($ev->type === 'finished'): ?>
                        <?= e(\Aero\Tracking\Models\Feed::PHASES[$d['outcome'] ?? ''] ?? '') ?>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>
</div>
