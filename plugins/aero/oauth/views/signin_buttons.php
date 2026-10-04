<div style="margin-top:16px;text-align:center">
    <p style="margin:0 0 10px;opacity:.7">o</p>
    <?php foreach ($providers as $provider): ?>
        <a href="<?= e(url('aero/oauth/' . $provider->code() . '/redirect')) ?>"
           class="btn btn-default btn-block" style="display:block">
            Continuar con <?= e($provider->label()) ?>
        </a>
    <?php endforeach ?>
</div>
