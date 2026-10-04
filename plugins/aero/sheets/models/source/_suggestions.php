<?php $list = \Aero\Sheets\Classes\SourceRegistry::suggestions(); if ($list): ?>
<div class="callout callout-info"><div class="content">
    <p>Modelos sugeridos por los plugins:</p>
    <ul><?php foreach ($list as $class => $label): ?><li><?= e($label) ?> — <code><?= e($class) ?></code></li><?php endforeach ?></ul>
</div></div>
<?php endif ?>
