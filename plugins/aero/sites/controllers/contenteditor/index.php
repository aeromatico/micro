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
$indexPage           = $this->vars['indexPage'];
$tenant              = $this->vars['tenant'];
$paletteVars         = $this->vars['paletteVars'] ?? [];
$archetypes          = $this->vars['archetypes'];
$aiConnectors        = $this->vars['aiConnectors'];
$lastGeneration      = $this->vars['lastGeneration'];

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

// Prompt de arranque para quien no elige ningún arquetipo — sale del nicho
// del propio tenant (specs/niches/{handle}.yaml vía NicheManager), no de un
// registro de Archetype, para que ya venga afín al rubro del negocio en vez
// de un texto genérico sin relación con el nicho.
$genericBasePrompt = app(\Aero\Sites\Classes\Niches\NicheManager::class)
    ->make($tenant->niche_type)
    ->getBasePrompt();

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
                <li>
                    <a href="#tab-branding" data-toggle="tab">
                        <i class="icon-image"></i> Branding
                    </a>
                </li>
                <li>
                    <a href="#tab-componentes" data-toggle="tab">
                        <i class="icon-th-large"></i> Componentes
                    </a>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ============================================================
                     TAB: INICIO
                     ============================================================ -->
                <div id="tab-inicio" class="tab-pane active">
                    <div class="layout padded-container">

                        <!-- ====================================================
                             AI GENERATION PANEL
                             ==================================================== -->
                        <?php if (!$hasExistingDesign): ?>
                        <div id="ai-panel" style="margin-bottom:28px">
                            <h4 style="margin-top:0">Generá tu primer diseño con Inteligencia Artificial</h4>
                            <p class="text-muted">Describe tu negocio en detalle y la IA generará automáticamente la página de inicio usando los bloques disponibles. Cuanta más información des, mejor será el resultado.</p>
                            <?= $this->makePartial('ai_form', [
                                'archetypes'         => $archetypes,
                                'tenant'             => $tenant,
                                'aiConnectors'       => $aiConnectors,
                                'genericBasePrompt'  => $genericBasePrompt,
                                'buttonLabel'        => 'Generar mi diseño con IA',
                                'confirm'            => null,
                            ]) ?>
                        </div>
                        <?php else: ?>
                        <div id="ai-panel" style="margin-bottom:28px">
                            <h4 style="margin-top:0; cursor:pointer" data-ai-toggle>
                                Reconstruir sitio con Inteligencia Artificial
                                <i class="icon-chevron-down"></i>
                            </h4>
                            <div data-ai-body style="display:none">
                                <p class="text-muted">
                                    Esto <strong>reemplaza por completo</strong> la página de inicio actual (bloques y contenido) por un nuevo diseño generado por IA. No es un ajuste parcial: cualquier edición manual hecha en el editor visual se perderá.
                                </p>
                                <?= $this->makePartial('ai_form', [
                                    'archetypes'         => $archetypes,
                                    'tenant'             => $tenant,
                                    'aiConnectors'       => $aiConnectors,
                                    'genericBasePrompt'  => $genericBasePrompt,
                                    'buttonLabel'        => 'Reconstruir con IA',
                                    'confirm'            => '¿Reconstruir la página de inicio? Se perderá el diseño y las ediciones actuales.',
                                    // "Rehacer con IA" (botón de acceso rápido junto a
                                    // Guardar) precarga esto y dispara el submit directo,
                                    // sin que el usuario tenga que reescribir el prompt.
                                    'lastPrompt'         => $lastGeneration->prompt ?? '',
                                    'lastArchetypeHandle'=> $lastGeneration->archetype_handle ?? '',
                                    'lastConnectorId'    => $lastGeneration->connector_id ?? null,
                                ]) ?>
                            </div>
                        </div>
                        <?php endif ?>

                        <script>
                        (function () {
                            var POLL_INTERVAL_MS = 2500;

                            function setGenerating(isGenerating) {
                                var btn = document.getElementById('ai-generate-btn');
                                if (btn) btn.disabled = isGenerating;
                            }

                            function poll(logId) {
                                oc.request('#ai-panel', 'onCheckAiStatus', {
                                    data: { log_id: logId },
                                    success: function (data) {
                                        if (data.status === 'pending' || data.status === 'processing') {
                                            setTimeout(function () { poll(logId); }, POLL_INTERVAL_MS);
                                        } else if (data.status === 'done') {
                                            // El widget del editor Puck de abajo quedó con los datos
                                            // viejos (se renderizó server-side al cargar la página).
                                            // Recargamos para que muestre el diseño recién generado;
                                            // si no, un "Guardar" posterior pisaría el resultado de la IA.
                                            setTimeout(function () { window.location.reload(); }, 1200);
                                        } else {
                                            setGenerating(false);
                                        }
                                    },
                                    error: function () {
                                        setGenerating(false);
                                    },
                                });
                            }

                            window.aeroAiOnGenerateStarted = function (data) {
                                if (!data || !data.aiLogId) return;
                                setGenerating(true);
                                poll(data.aiLogId);
                            };

                            document.querySelectorAll('[data-ai-toggle]').forEach(function (toggle) {
                                toggle.addEventListener('click', function () {
                                    var panel = toggle.closest('#ai-panel');
                                    var body = panel ? panel.querySelector('[data-ai-body]') : null;
                                    if (body) body.style.display = (body.style.display === 'none') ? '' : 'none';
                                });
                            });

                            var archetypeSelect = document.getElementById('ai-archetype-select');
                            var archetypeDescription = document.getElementById('ai-archetype-description');
                            var promptTextarea = document.getElementById('ai-prompt-textarea');
                            // Aplica el prompt base asociado a la opción elegida.
                            // - force=true (el usuario cambió de arquetipo): reemplaza
                            //   siempre, para que el campo refleje al instante el prompt
                            //   realmente asociado a esa opción.
                            // - force=false (carga inicial): sólo autocompleta si el campo
                            //   viene vacío, así no pisa el último prompt usado en
                            //   "Rehacer con IA".
                            function applyArchetypePrompt(force) {
                                var opt = archetypeSelect.options[archetypeSelect.selectedIndex];
                                archetypeDescription.textContent = (opt && opt.dataset.description) || '';

                                if (!promptTextarea) return;
                                var basePrompt = (opt && opt.dataset.basePrompt) || '';
                                if (force || promptTextarea.value.trim() === '') {
                                    promptTextarea.value = basePrompt;
                                }
                            }
                            if (archetypeSelect && archetypeDescription) {
                                archetypeSelect.addEventListener('change', function () {
                                    applyArchetypePrompt(true);
                                });
                                // Precarga desde el inicio: la opción seleccionada por defecto
                                // ("Sin arquetipo") también trae un prompt genérico del nicho.
                                applyArchetypePrompt(false);
                            }

                            // Delegado en document: el botón vive más abajo en el DOM
                            // (dentro del form de "Guardar página de inicio"), y este
                            // script corre antes de que exista — no se puede hacer
                            // getElementById acá arriba.
                            document.addEventListener('click', function (e) {
                                if (!e.target.closest('#ai-redo-btn')) return;

                                var panel = document.getElementById('ai-panel');
                                var body = panel ? panel.querySelector('[data-ai-body]') : null;
                                if (body) body.style.display = '';
                                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

                                // El form ya viene precargado con el último prompt/arquetipo
                                // usado (ver ContentEditor::index() → $lastGeneration), así
                                // que "Rehacer con IA" dispara el submit directo — el usuario
                                // solo confirma el diálogo de "esto reemplaza el diseño
                                // actual", no tiene que reescribir nada.
                                var generateBtn = document.getElementById('ai-generate-btn');
                                if (generateBtn) generateBtn.click();
                            });
                        })();
                        </script>

                        <?php if ($indexPage): ?>
                        <form data-request="onSaveIndex" data-request-flash>
                            <?= $indexPageWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
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
                     poder ajustar paleta/tipografía y ver el resultado ahí mismo)
                     ============================================================ -->
                <div id="tab-branding" class="tab-pane">
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
                     TAB: COMPONENTES (galería de referencia de bloques Puck,
                     embebida de aero/sites/componentgallery — ver
                     componentgallery/_gallery.php)
                     ============================================================ -->
                <div id="tab-componentes" class="tab-pane">
                    <?php
                    $blocks       = $this->vars['blocks'];
                    $themes       = $this->vars['themes'];
                    $defaultTheme = $this->vars['defaultThemeHandle'];
                    $previewUrl   = \Backend::url('aero/sites/componentgallery/preview');
                    $firstBlock   = array_key_first($blocks);
                    include __DIR__ . '/../componentgallery/_gallery.php';
                    ?>
                </div><!-- /#tab-componentes -->

            </div><!-- /.tab-content (main) -->
        </div><!-- /.control-tabs (main) -->
    </div>
</div>
