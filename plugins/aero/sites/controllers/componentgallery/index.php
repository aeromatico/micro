<?php
/** @var Aero\Sites\Controllers\ComponentGallery $this */
$blocks       = $this->vars['blocks'];
$themes       = $this->vars['themes'];
$defaultTheme = $this->vars['defaultThemeHandle'];
$previewUrl   = \Backend::url('aero/sites/componentgallery/preview');
$firstBlock   = array_key_first($blocks);
?>
<style>[x-cloak] { display: none !important; }</style>
<div class="padded-container" x-data="componentGallery()" x-init="init()">

    <div class="callout fade in callout-info no-subheader" style="margin-bottom:20px">
        <div class="header">
            <i class="icon-info-circle"></i>
            <h4>Galería interna de variantes</h4>
            <p>Cada preview usa el CSS real de <code>themes/microsites</code> y el mismo renderizador
            (<code>PuckHtmlRenderer.php</code>) que el editor visual y el generador con IA — lo que ves acá
            es exactamente lo que verá el cliente. Elegí un bloque abajo: solo se cargan los iframes del
            bloque activo para no saturar la página.</p>
        </div>
    </div>

    <!-- ==================================================================
         CONTROLES GLOBALES
         ================================================================== -->
    <div class="form-inline" style="display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap; margin-bottom:20px; padding:15px; background:var(--content-bg, #fff); border:1px solid var(--border-color, #e2e8f0); border-radius:6px;">
        <div>
            <label style="display:block; font-weight:600; margin-bottom:4px;">Tema visual</label>
            <select class="form-control" x-model="theme" style="min-width:220px">
                <?php foreach ($themes as $t): ?>
                    <option value="<?= e($t->handle) ?>"><?= e($t->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-weight:600; margin-bottom:4px;">Modo</label>
            <div class="btn-group">
                <button type="button" class="btn btn-default" :class="{ 'btn-primary': mode === 'light' }" @click="mode = 'light'">
                    <i class="icon-sun-o"></i> Claro
                </button>
                <button type="button" class="btn btn-default" :class="{ 'btn-primary': mode === 'dark' }" @click="mode = 'dark'">
                    <i class="icon-moon-o"></i> Oscuro
                </button>
            </div>
        </div>
        <div>
            <label style="display:block; font-weight:600; margin-bottom:4px;">Ancho de vista</label>
            <div class="btn-group">
                <button type="button" class="btn btn-default" :class="{ 'btn-primary': width === 375 }" @click="width = 375">Mobile</button>
                <button type="button" class="btn btn-default" :class="{ 'btn-primary': width === 768 }" @click="width = 768">Tablet</button>
                <button type="button" class="btn btn-default" :class="{ 'btn-primary': width === 0 }" @click="width = 0">Desktop</button>
            </div>
        </div>
    </div>

    <!-- ==================================================================
         SELECTOR DE BLOQUE — solo el bloque activo renderiza sus iframes
         ================================================================== -->
    <div class="btn-group" style="margin-bottom:20px; flex-wrap:wrap;">
        <?php foreach ($blocks as $key => $block): ?>
            <button type="button" class="btn btn-default" :class="{ 'btn-primary': activeBlock === '<?= e($key) ?>' }" @click="activeBlock = '<?= e($key) ?>'">
                <?= e($block['label']) ?> <span class="text-muted">(<?= count($block['variants']) ?>)</span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($blocks as $key => $block): ?>
    <div x-show="activeBlock === '<?= e($key) ?>'" x-cloak>
        <h3 style="margin-bottom:15px"><?= e($block['label']) ?> <small class="text-muted"><?= count($block['variants']) ?> variantes de layout</small></h3>

        <div style="display:grid; grid-template-columns:repeat(<?= (int) $block['columns'] ?>, minmax(0, 1fr)); gap:20px; margin-bottom:40px;">
            <?php foreach ($block['variants'] as $value => $label): ?>
            <div class="callout fade in no-subheader" style="padding:0; overflow:hidden;" x-data="{ codeOpen: false, copied: false }">
                <div style="padding:12px 15px; border-bottom:1px solid var(--border-color, #e2e8f0); display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <strong><?= e($label) ?></strong>
                    <code style="font-size:11px; opacity:.6;"><?= e($value) ?></code>
                </div>

                <div style="background:#eef1f4; padding:16px; display:flex; justify-content:center;">
                    <div :style="frameWrapperStyle()">
                        <iframe
                            :src="previewUrl('<?= e($key) ?>', '<?= e($value) ?>')"
                            style="width:100%; height:<?= (int) $block['previewHeight'] ?>px; border:0; border-radius:4px; background:#fff;"
                            loading="lazy"
                        ></iframe>
                    </div>
                </div>

                <div style="border-top:1px solid var(--border-color, #e2e8f0);">
                    <button type="button" class="btn btn-link" style="padding:8px 15px; width:100%; text-align:left;" @click="codeOpen = !codeOpen">
                        <i class="icon-code"></i> Ver código (JSON de props)
                        <i class="icon-chevron-down pull-right" :class="{ 'icon-chevron-up': codeOpen }"></i>
                    </button>
                    <div x-show="codeOpen" style="padding:0 15px 15px;">
                        <div style="position:relative;">
                            <pre style="background:#1e293b; color:#e2e8f0; padding:12px; border-radius:4px; font-size:12px; max-height:260px; overflow:auto;"><code x-text="propsJson('<?= e($key) ?>', '<?= e($value) ?>')"></code></pre>
                            <button type="button" class="btn btn-default btn-sm" style="position:absolute; top:8px; right:8px;"
                                    @click="copyProps('<?= e($key) ?>', '<?= e($value) ?>', $event)">
                                <span x-show="!copied"><i class="icon-copy"></i> Copiar</span>
                                <span x-show="copied" style="color:#22c55e"><i class="icon-check"></i> Copiado</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="callout fade in callout-default no-subheader">
        <div class="header">
            <h4>Resto del catálogo</h4>
            <p class="text-muted">Próximamente — se irán agregando galerías de variantes bloque por bloque siguiendo este mismo patrón.</p>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function componentGallery() {
    return {
        theme: <?= json_encode($defaultTheme) ?>,
        mode: 'light',
        width: 0,
        activeBlock: <?= json_encode($firstBlock) ?>,
        defaultProps: {
            <?php foreach ($blocks as $key => $block): ?>
            <?= json_encode($key) ?>: <?= json_encode($block['defaultProps'], JSON_UNESCAPED_SLASHES) ?>,
            <?php endforeach; ?>
        },
        copied: false,

        init() {},

        frameWrapperStyle() {
            return this.width ? `width:${this.width}px; max-width:100%;` : 'width:100%;';
        },

        previewUrl(block, variant) {
            const params = new URLSearchParams({
                block: block,
                variant: variant,
                theme: this.theme || '',
                mode: this.mode,
            });
            return '<?= $previewUrl ?>?' + params.toString();
        },

        propsFor(block, variant) {
            return { ...this.defaultProps[block], variant };
        },

        propsJson(block, variant) {
            return JSON.stringify(this.propsFor(block, variant), null, 2);
        },

        copyProps(block, variant, evt) {
            const text = this.propsJson(block, variant);
            navigator.clipboard.writeText(text).then(() => {
                const scope = Alpine.$data(evt.target.closest('[x-data]'));
                scope.copied = true;
                setTimeout(() => { scope.copied = false; }, 1500);
            });
        },
    };
}
</script>
