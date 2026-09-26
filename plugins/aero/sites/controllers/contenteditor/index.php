<?php
/** @var Aero\Sites\Controllers\ContentEditor $this */
if (!empty($this->vars['noTenant'])): ?>
<div class="padded-container">
    <p class="text-muted">
        No hay ningún tenant asociado al sitio activo. Selecciona un sitio con tenant en el selector de sitios del backend.
    </p>
</div>
<?php return; endif;

$indexPageWidget     = $this->indexPageWidget;
$brandingWidget      = $this->brandingWidget;
$layoutWidget        = $this->layoutWidget;
$indexPage           = $this->vars['indexPage'];
$layout              = $this->vars['layout'];
$paletteVars         = $this->vars['paletteVars'] ?? [];
$defaultBaseHtml     = $this->vars['defaultBaseHtml'] ?? '';
$defaultHeaderHtml   = $this->vars['defaultHeaderHtml'] ?? '';
$defaultFooterHtml   = $this->vars['defaultFooterHtml'] ?? '';

$paletteSwatchKeys = [
    '--color-primary'   => 'Primario',
    '--color-secondary' => 'Secundario',
    '--color-accent'    => 'Acento',
    '--color-surface-bg' => 'Fondo',
    '--color-surface-alt' => 'Panel',
];

// Un sitio "ya construido" tiene página + al menos un bloque en el editor
// visual. Se usa para distinguir el primer lanzamiento (CTA grande, siempre
// visible) de una reconstrucción (panel colapsado + confirmación, porque
// reemplaza el diseño y descarta ediciones manuales — SiteGenerator siempre
// regenera la página completa desde cero, no hay modo "actualizar parcial").
$hasExistingDesign = $indexPage && !empty($indexPage->puck_data);

// Con plantilla propia (HTML completo) no hay editor visual: el tenant pegó
// su propio documento, así que la galería de bloques Puck no aplica — se
// deshabilita la pestaña en vez de dejarla ahí mostrando algo que no se
// puede usar. Se re-habilita apenas vuelve a modo "Plataforma".
$isCustomLayout = $layout && $layout->mode === 'custom' && $layout->custom_html;
?>
<div class="layout-row">
    <div class="layout-cell">
        <div class="control-tabs master-tabs" data-control="tab">

            <!-- Tab nav -->
            <ul class="nav nav-tabs">
                <li class="active">
                    <a href="#tab-inicio" data-toggle="tab">
                        <i class="icon-home"></i> Inicio
                    </a>
                </li>
                <li id="tab-nav-branding" style="<?= $isCustomLayout ? 'display:none' : '' ?>">
                    <a href="#tab-branding" data-toggle="tab">
                        <i class="icon-image"></i> Branding
                    </a>
                </li>
                <li id="tab-nav-bloques" style="<?= $isCustomLayout ? 'display:none' : '' ?>">
                    <a href="#tab-componentes" data-toggle="tab">
                        <i class="icon-th-large"></i> Bloques
                    </a>
                </li>
                <li>
                    <a href="#tab-plantilla" data-toggle="tab">
                        <i class="icon-code"></i> Plantilla
                    </a>
                </li>
            </ul>

            <script>
            (function () {
                // El modo de "Plantilla" (Layout.mode) se cambia en vivo desde el
                // balloon-selector de la pestaña Plantilla, sin recargar la
                // página — así que Branding/Bloques (que no aplican con plantilla
                // propia) tienen que esconderse/mostrarse al toque, no solo al
                // recargar después de guardar.
                //
                // balloon-selector.js hace $field.val(value).trigger('change') —
                // ese 'change' es un evento sintético de jQuery, NO uno nativo
                // del DOM (verificado con Playwright: dispara los handlers
                // .on('change', ...) de jQuery pero jamás llega a un
                // document.addEventListener('change', ...) nativo). Por eso
                // esto tiene que engancharse con jQuery, delegado en document
                // porque el campo vive más abajo en el DOM (dentro del <form>
                // de la pestaña Plantilla) y este script corre antes de que
                // exista.
                function setHidden(id, hidden) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = hidden ? 'none' : '';
                }

                function onModeChange(value) {
                    var isCustom = value === 'custom';

                    setHidden('tab-nav-branding', isCustom);
                    setHidden('tab-nav-bloques', isCustom);
                    setHidden('tab-branding', isCustom);
                    setHidden('tab-componentes', isCustom);

                    // Con plantilla propia tampoco tiene sentido el editor
                    // visual (ni la IA que lo alimenta) en el tab Inicio: se
                    // esconde el selector "Tipo de contenido" y el panel de
                    // IA, y se fuerza el valor a "Código" — no queda otra
                    // opción visible, así que no tiene sentido dejar el campo
                    // en un valor que ya no se puede elegir. El "Código" en sí
                    // (content_raw) ya se muestra/oculta solo, vía su propio
                    // trigger nativo de October atado a "Tipo de contenido".
                    setHidden('Form-indexPageForm-field-IndexPage-content_mode-group', isCustom);
                    setHidden('Form-indexPageForm-field-IndexPage-ai_panel-group', isCustom);
                    if (isCustom && window.oc && oc.Events) {
                        var contentModeEl = document.getElementById('Form-indexPageForm-field-IndexPage-content_mode');
                        if (contentModeEl) {
                            oc.Events.dispatch('trigger:fill', { target: contentModeEl, detail: { fillValue: 'code' } });
                        }
                    }

                    // Si Branding o Bloques estaban activas cuando se ocultan,
                    // hay que volver a Inicio — si no, queda un tab activo sin
                    // nav visible y sin forma de salir de ahí con un clic.
                    if (isCustom) {
                        var activePane = document.querySelector('#tab-branding.active, #tab-componentes.active');
                        if (activePane) {
                            var inicioLink = document.querySelector('a[href="#tab-inicio"]');
                            if (inicioLink) inicioLink.click();
                        }
                    }
                }

                if (window.jQuery) {
                    jQuery(document).on('change', '[name="Layout[mode]"]', function () {
                        onModeChange(this.value);
                    });

                    // Pasada inicial: si la plantilla ya está guardada como
                    // "custom" pero la página de inicio todavía tiene guardado
                    // content_mode=puck de antes (se guardan por separado, en
                    // pestañas distintas), sin esto el selector quedaría oculto
                    // pero el editor visual seguiría mostrándose, sin forma de
                    // llegar al campo de código.
                    <?php if ($isCustomLayout): ?>
                    jQuery(function () { onModeChange('custom'); });
                    <?php endif ?>
                }
            })();
            </script>

            <div class="tab-content">

                <!-- ============================================================
                     TAB: INICIO
                     ============================================================ -->
                <div id="tab-inicio" class="tab-pane active">
                    <div class="layout padded-container">

                        <?php if ($indexPageWidget): ?>
                        <?php if (!$indexPage): ?>
                        <p class="text-muted">
                            Todavía no tienes una página de inicio. Describe tu negocio abajo y genera tu sitio con IA,
                            o escribe el contenido a mano y guarda para crearla.
                        </p>
                        <?php endif ?>
                        <form data-request="onSaveIndex" data-request-flash>
                            <?= $indexPageWidget->render() ?>
                            <div
                                id="ai-generating-notice"
                                style="display:none; align-items:center; gap:10px; padding:10px 14px; margin-bottom:10px; border-radius:8px; border:1px solid rgba(99,102,241,.35); background:rgba(99,102,241,.08)"
                            >
                                <i class="icon-spinner icon-spin" style="color:#6366f1"></i>
                                <span>Generando con IA — "Guardar" queda bloqueado hasta que termine, para no pisar el resultado con lo que había antes.</span>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" id="save-index-btn" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar página de inicio
                                </button>
                                <?php if ($hasExistingDesign): ?>
                                <button type="button" id="ai-redo-btn" class="btn btn-default">
                                    <i class="icon-refresh"></i> Rehacer con IA
                                </button>
                                <?php endif ?>
                            </div>
                        </form>
                        <?php else: ?>
                        <p class="text-muted">No se encontró la página de inicio para este sitio.</p>
                        <?php endif ?>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: BRANDING (identidad visual — al lado del diseño para
                     poder ajustar paleta/tipografía y ver el resultado ahí
                     mismo). Oculta por completo con plantilla propia (HTML
                     completo): esa paleta solo la consume el editor visual
                     y el theme por defecto, ninguno de los dos aplica.
                     ============================================================ -->
                <div id="tab-branding" class="tab-pane" style="<?= $isCustomLayout ? 'display:none' : '' ?>">
                    <div class="layout padded-container">
                        <?php if ($paletteVars): ?>
                        <div style="margin-bottom:20px">
                            <h5 style="margin-bottom:8px">Vista previa de la paleta activa</h5>
                            <?php foreach (['light' => 'Modo claro', 'dark' => 'Modo oscuro'] as $mode => $modeLabel): ?>
                            <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px">
                                <span class="text-muted" style="width:90px;font-size:12px"><?= e($modeLabel) ?></span>
                                <?php foreach ($paletteSwatchKeys as $var => $swatchLabel): ?>
                                <span
                                    title="<?= e($swatchLabel) ?>: <?= e($paletteVars[$mode][$var] ?? '') ?>"
                                    style="display:inline-block;width:28px;height:28px;border-radius:6px;border:1px solid rgba(0,0,0,.15);background:<?= e($paletteVars[$mode][$var] ?? 'transparent') ?>"
                                ></span>
                                <?php endforeach ?>
                            </div>
                            <?php endforeach ?>
                        </div>
                        <?php endif ?>
                        <form data-request="onSaveBranding" data-request-flash>
                            <?= $brandingWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar branding
                                </button>
                            </div>
                        </form>
                    </div>
                </div><!-- /#tab-branding -->

                <!-- ============================================================
                     TAB: BLOQUES (galería de referencia de bloques Puck,
                     embebida de aero/sites/componentgallery — ver
                     componentgallery/_gallery.php). Oculta por completo con
                     plantilla propia (HTML completo): no hay editor visual
                     sobre el que aplicar bloques.
                     ============================================================ -->
                <div id="tab-componentes" class="tab-pane" style="<?= $isCustomLayout ? 'display:none' : '' ?>">
                    <?php
                    $blocks       = $this->vars['blocks'];
                    $themes       = $this->vars['themes'];
                    $defaultTheme = $this->vars['defaultThemeHandle'];
                    $previewUrl   = \Backend::url('aero/sites/componentgallery/preview');
                    $firstBlock   = array_key_first($blocks);
                    include __DIR__ . '/../componentgallery/_gallery.php';
                    ?>
                </div><!-- /#tab-componentes -->

                <!-- ============================================================
                     TAB: PLANTILLA (gestor de layouts — Aero\Sites\Models\Layout)
                     ============================================================ -->
                <div id="tab-plantilla" class="tab-pane">
                    <div class="layout padded-container">

                        <div style="margin-bottom:20px">
                            <h4 style="margin-top:0; cursor:pointer" data-toggle-ref>
                                Referencia: lo que se sirve hoy por defecto
                                <i class="icon-chevron-down"></i>
                            </h4>
                            <div data-ref-body style="display:none">
                                <p class="text-muted">
                                    Esto es exactamente lo que corre hoy cuando el modo es "Plataforma" y no escribiste header/footer propio. <code>base.htm</code> es una plantilla Twig (no se puede pegar tal cual en "Documento HTML completo" — ese campo es HTML puro); <code>header.htm</code> y <code>footer.htm</code> mezclan HTML con datos del tenant (nombre, logo, páginas, contacto), así que si escribes tu propio header/footer pierdes esa parte dinámica salvo que la repliques tú mismo.
                                </p>
                                <div class="control-tabs" data-control="tab">
                                    <ul class="nav nav-tabs">
                                        <li class="active"><a href="#ref-base" data-toggle="tab">layouts/base.htm</a></li>
                                        <li><a href="#ref-header" data-toggle="tab">partials/site/header.htm</a></li>
                                        <li><a href="#ref-footer" data-toggle="tab">partials/site/footer.htm</a></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="ref-base" class="tab-pane active"><pre style="max-height:360px; overflow:auto; font-size:12px"><?= e($defaultBaseHtml) ?></pre></div>
                                        <div id="ref-header" class="tab-pane"><pre style="max-height:360px; overflow:auto; font-size:12px"><?= e($defaultHeaderHtml) ?></pre></div>
                                        <div id="ref-footer" class="tab-pane"><pre style="max-height:360px; overflow:auto; font-size:12px"><?= e($defaultFooterHtml) ?></pre></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                        document.querySelectorAll('[data-toggle-ref]').forEach(function (toggle) {
                            toggle.addEventListener('click', function () {
                                var body = toggle.parentElement.querySelector('[data-ref-body]');
                                if (body) body.style.display = (body.style.display === 'none') ? '' : 'none';
                            });
                        });
                        </script>

                        <?php if ($layout): ?>
                        <?php if (!$layout->header_html && !$layout->footer_html): ?>
                        <p class="text-muted" style="margin-bottom:14px">
                            <i class="icon-info-circle"></i>
                            "Header / navbar propio" y "Footer propio" están vacíos: eso significa que ahora mismo se está usando el navbar y el footer <strong>por defecto</strong> (los de arriba, en "Referencia"), no que falte algo. Si quieres partir de ese mismo diseño para tocarlo, usa los botones "Cargar el actual" de cada campo — te traen el HTML tal como se ve hoy, ya con tu nombre/logo/páginas.
                        </p>
                        <?php endif ?>
                        <form data-request="onSaveLayout" data-request-flash>
                            <?= $layoutWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar plantilla
                                </button>
                                <button type="button" id="load-default-header-btn" class="btn btn-default">
                                    <i class="icon-download"></i> Cargar el navbar actual
                                </button>
                                <button type="button" id="load-default-footer-btn" class="btn btn-default">
                                    <i class="icon-download"></i> Cargar el footer actual
                                </button>
                            </div>
                        </form>

                        <script>
                        (function () {
                            function setCodeEditorContent(fieldSuffix, html) {
                                var el = document.querySelector('[id$="-' + fieldSuffix + '"][data-control="codeeditor"]');
                                var instance = el && window.jQuery && jQuery(el).data('oc.codeEditor');
                                if (instance) {
                                    instance.setContent(html);
                                } else if (el) {
                                    // Fallback si el editor visual todavía no inicializó (campo
                                    // oculto por el trigger de "Modo"): el textarea real sigue
                                    // ahí debajo, ace lo relee al mostrarse.
                                    var textarea = el.querySelector('textarea');
                                    if (textarea) textarea.value = html;
                                }
                            }

                            document.addEventListener('click', function (e) {
                                var btn = e.target.closest('#load-default-header-btn, #load-default-footer-btn');
                                if (!btn) return;
                                e.preventDefault();

                                var isHeader = btn.id === 'load-default-header-btn';
                                oc.request(btn, isHeader ? 'onLoadDefaultHeader' : 'onLoadDefaultFooter', {
                                    success: function (data) {
                                        setCodeEditorContent(isHeader ? 'header_html' : 'footer_html', data.html || '');
                                    },
                                });
                            });
                        })();
                        </script>
                        <?php endif ?>
                    </div>
                </div><!-- /#tab-plantilla -->

            </div><!-- /.tab-content (main) -->
        </div><!-- /.control-tabs (main) -->
    </div>
</div>
