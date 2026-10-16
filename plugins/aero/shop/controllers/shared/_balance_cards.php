<?php /** @var array $balance */ ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
    <?php foreach ([
        'Cobrado'       => $balance['collected'],
        'Comisión'      => $balance['commission'],
        'Neto'          => $balance['net'],
        'Por cobrar'    => $balance['pending'],
        'Reembolsado'   => $balance['refunded'],
    ] as $label => $value): ?>
        <div style="border:1px solid #e3e6ea;border-radius:8px;padding:12px 14px;background:#fff">
            <div style="font-size:12px;color:#6b7280"><?= e($label) ?></div>
            <div style="font-size:22px;font-weight:700"><?= number_format($value, 2) ?></div>
        </div>
    <?php endforeach ?>
</div>
