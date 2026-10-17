<!doctype html>
<html lang="es"><head><meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .box { border: 1px solid #444; padding: 6px 8px; }
    .items th { background: #eee; border: 1px solid #444; padding: 4px; text-align: left; }
    .items td { border: 1px solid #999; padding: 4px; }
    .r { text-align: right; }
    .muted { color: #555; }
    .mark { text-align: center; color: #b00; font-size: 14px; font-weight: bold; border: 2px solid #b00; padding: 4px; margin-bottom: 8px; }
    .small { font-size: 8px; color: #555; }
</style></head><body>
<?php if (!$fiscal): ?><div class="mark">SIN VALOR FISCAL — <?= $esc($invoice->statusLabel) ?></div><?php endif ?>
<?php if ($invoice->status === 'voided'): ?><div class="mark">FACTURA ANULADA</div><?php endif ?>
<?php if ($issuer->environment === 'test'): ?><div class="mark">AMBIENTE DE PRUEBAS</div><?php endif ?>

<table class="head"><tr>
    <td style="width:60%">
        <h1>FACTURA</h1>
        <div><strong><?= $esc($issuer->legal_name) ?></strong></div>
        <?php if ($issuer->trade_name): ?><div><?= $esc($issuer->trade_name) ?></div><?php endif ?>
        <div>Sucursal N.º <?= (int) $issuer->branch_code ?> · Punto de venta <?= (int) $issuer->pos_code ?></div>
        <?php if ($issuer->address): ?><div><?= $esc($issuer->address) ?></div><?php endif ?>
        <?php if ($issuer->municipality): ?><div><?= $esc($issuer->municipality) ?></div><?php endif ?>
        <?php if ($issuer->phone): ?><div>Tel. <?= $esc($issuer->phone) ?></div><?php endif ?>
    </td>
    <td class="box">
        <div>NIT: <strong><?= $esc($issuer->nit) ?></strong></div>
        <div>Factura N.º: <strong><?= $esc($invoice->number ?: '—') ?></strong></div>
        <div style="word-break:break-all">CUF: <?= $esc($invoice->cuf ?: '—') ?></div>
    </td>
</tr></table>

<table class="box" style="margin-top:8px"><tr>
    <td>Fecha: <?= $esc(($invoice->issued_at ?: $invoice->created_at)?->format('d/m/Y H:i')) ?></td>
    <td>Nombre / Razón social: <strong><?= $esc($invoice->buyer_name) ?></strong></td>
    <td>NIT/CI: <strong><?= $esc($invoice->buyer_nit) ?></strong></td>
</tr></table>

<table class="items" style="margin-top:8px">
    <tr><th>Cód. SIN</th><th>Descripción</th><th class="r">Cant.</th><th class="r">P. unitario</th><th class="r">Subtotal</th></tr>
    <?php foreach ($items as $item): ?>
        <?php $sub = round((float) $item['quantity'] * (float) $item['unit_price'], 2) ?>
        <tr>
            <td><?= $esc($item['sin_product_code'] ?? '') ?></td>
            <td><?= $esc($item['description'] ?? '') ?></td>
            <td class="r"><?= $esc(rtrim(rtrim(number_format((float) $item['quantity'], 4, '.', ''), '0'), '.')) ?></td>
            <td class="r"><?= $money($item['unit_price']) ?></td>
            <td class="r"><?= $money($sub) ?></td>
        </tr>
    <?php endforeach ?>
    <tr><td colspan="4" class="r"><strong>TOTAL Bs</strong></td><td class="r"><strong><?= $money($invoice->total) ?></strong></td></tr>
</table>

<table style="margin-top:8px"><tr>
    <td style="vertical-align:top">
        <div><?= $esc($words) ?></div>
        <div class="muted" style="margin-top:4px">Importe base crédito fiscal Bs <?= $money($invoice->total) ?> (IVA 13 % incluido: Bs <?= $money($invoice->iva) ?>)</div>
        <?php if ($invoice->reception_code): ?><div class="muted">Código de recepción: <?= $esc($invoice->reception_code) ?></div><?php endif ?>
    </td>
    <td style="width:130px;text-align:right">
        <?php if ($qr): ?><img src="<?= $qr ?>" style="width:120px;height:120px"><?php endif ?>
    </td>
</tr></table>

<p class="small">Representación gráfica de un documento fiscal digital emitido en una modalidad de facturación en línea. Se verifica con el código QR o el CUF en el portal del SIN.</p>
</body></html>
