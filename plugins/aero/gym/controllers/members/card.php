<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/gym/members') ?>">Socios</a></li><li>Carnet</li></ul>
<?php Block::endPut() ?>
<div style="max-width:340px;margin:20px auto;padding:24px;border:2px solid #333;border-radius:12px;text-align:center;background:#fff">
    <h3 style="margin:0 0 4px"><?= e($member->name) ?></h3>
    <div style="color:#666;margin-bottom:12px">Socio #<?= (int) $member->id ?></div>
    <div id="gym-qr" style="display:inline-block"></div>
    <div style="font-family:monospace;margin-top:10px;font-size:15px"><?= e($member->qr_token) ?></div>
    <?php if ($member->card_number): ?><div style="color:#666">Carnet <?= e($member->card_number) ?></div><?php endif ?>
</div>
<p style="text-align:center"><button class="btn btn-primary" onclick="window.print()">Imprimir</button></p>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('gym-qr'), {text: <?= json_encode($member->qr_token) ?>, width: 200, height: 200});</script>
