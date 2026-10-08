<?php $n = $record->bookedCount(); $w = $record->waitlistCount(); ?>
<?= $n ?>/<?= (int) $record->capacity ?><?php if ($w): ?> <small>(+<?= $w ?> en espera)</small><?php endif ?>
