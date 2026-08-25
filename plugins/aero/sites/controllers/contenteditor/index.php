<?php
/** @var Aero\Sites\Controllers\ContentEditor $this */
if (!empty($this->vars['noTenant'])): ?>
<div class="padded-container">
    <div class="alert alert-warning">
        <i class="icon-warning"></i>
        No hay ningún tenant asociado al sitio activo. Selecciona un sitio con tenant en el selector de sitios del backend.
    </div>
</div>
<?php return; endif;

$indexPageWidget     = $this->indexPageWidget;
$contactPageWidget   = $this->contactPageWidget;
$contactConfigWidget = $this->contactConfigWidget;
$indexPage           = $this->vars['indexPage'];
$contactPage         = $this->vars['contactPage'];
$submissions         = $this->vars['submissions'];
$archetypes          = $this->vars['archetypes'];

// Un sitio "ya construido" tiene página + al menos un bloque en el editor
// visual. Se usa para distinguir el primer lanzamiento (CTA grande, siempre
// visible) de una reconstrucción (panel colapsado + confirmación, porque
// reemplaza el diseño y descarta ediciones manuales — SiteGenerator siempre
// regenera la página completa desde cero, no hay modo "actualizar parcial").
$hasExistingDesign = $indexPage && !empty($indexPage->puck_data);

$statusLabels = [
    'pending' => ['label' => 'Pendiente', 'class' => 'warning'],
    'sent'    => ['label' => 'Enviado',   'class' => 'success'],
    'failed'  => ['label' => 'Fallido',   'class' => 'danger'],
    'partial' => ['label' => 'Parcial',   'class' => 'info'],
];
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
                    <a href="#tab-contacto" data-toggle="tab">
                        <i class="icon-phone"></i> Contacto
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
                        <div id="ai-panel" class="callout fade in callout-warning no-subheader" style="margin-bottom:20px">
                            <div class="header">
                                <i class="icon-magic"></i>
                                <h4>Generá tu primer diseño con Inteligencia Artificial</h4>
                            </div>
                            <div class="content">
                                <p>Describe tu negocio en detalle y la IA generará automáticamente la página de inicio usando los bloques disponibles. Cuanta más información des, mejor será el resultado.</p>
                                <?= $this->makePartial('ai_form', [
                                    'archetypes'  => $archetypes,
                                    'buttonLabel' => '✨ Generar mi diseño con IA',
                                    'confirm'     => null,
                                ]) ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div id="ai-panel" class="callout fade callout-warning no-subheader" style="margin-bottom:20px">
                            <div class="header" style="cursor:pointer" data-ai-toggle>
                                <i class="icon-magic"></i>
                                <h4>Reconstruir sitio con Inteligencia Artificial <i class="icon-chevron-down pull-right"></i></h4>
                            </div>
                            <div class="content" data-ai-body style="display:none">
                                <p class="text-warning">
                                    <i class="icon-warning"></i>
                                    Esto <strong>reemplaza por completo</strong> la página de inicio actual (bloques y contenido) por un nuevo diseño generado por IA. No es un ajuste parcial: cualquier edición manual hecha en el editor visual se perderá.
                                </p>
                                <?= $this->makePartial('ai_form', [
                                    'archetypes'  => $archetypes,
                                    'buttonLabel' => '🔄 Reconstruir con IA',
                                    'confirm'     => '¿Reconstruir la página de inicio? Se perderá el diseño y las ediciones actuales.',
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
                                    var body = toggle.parentElement.querySelector('[data-ai-body]');
                                    if (body) body.style.display = (body.style.display === 'none') ? '' : 'none';
                                });
                            });

                            var archetypeSelect = document.getElementById('ai-archetype-select');
                            var archetypeDescription = document.getElementById('ai-archetype-description');
                            if (archetypeSelect && archetypeDescription) {
                                archetypeSelect.addEventListener('change', function () {
                                    var opt = archetypeSelect.options[archetypeSelect.selectedIndex];
                                    archetypeDescription.textContent = (opt && opt.dataset.description) || '';
                                });
                            }
                        })();
                        </script>

                        <?php if ($indexPage): ?>
                        <form data-request="onSaveIndex" data-request-flash>
                            <?= $indexPageWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar página de inicio
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            No se encontró la página de inicio para este sitio.
                        </div>
                        <?php endif ?>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: CONTACTO  (sub-tabs: Página / Formulario / Mensajes)
                     ============================================================ -->
                <div id="tab-contacto" class="tab-pane">
                    <div class="control-tabs" data-control="tab">

                        <ul class="nav nav-tabs secondary-tabs">
                            <li class="active">
                                <a href="#subtab-pagina" data-toggle="tab">
                                    <i class="icon-file-text-o"></i> Página
                                </a>
                            </li>
                            <li>
                                <a href="#subtab-formulario" data-toggle="tab">
                                    <i class="icon-sliders"></i> Formulario
                                </a>
                            </li>
                            <li>
                                <a href="#subtab-mensajes" data-toggle="tab">
                                    <i class="icon-envelope"></i> Mensajes
                                    <?php if ($submissions->isNotEmpty()): ?>
                                    <span class="badge"><?= $submissions->count() ?></span>
                                    <?php endif ?>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">

                            <!-- Sub-tab: Página de contacto -->
                            <div id="subtab-pagina" class="tab-pane active">
                                <div class="layout padded-container">
                                    <?php if ($contactPage): ?>
                                    <form data-request="onSaveContactPage" data-request-flash>
                                        <?= $contactPageWidget->render() ?>
                                        <div class="form-buttons">
                                            <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                                <i class="icon-check"></i> Guardar página de contacto
                                            </button>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        No se encontró la página de contacto para este sitio.
                                    </div>
                                    <?php endif ?>
                                </div>
                            </div>

                            <!-- Sub-tab: Configuración del formulario -->
                            <div id="subtab-formulario" class="tab-pane">
                                <div class="layout padded-container">
                                    <form data-request="onSaveContactConfig" data-request-flash>
                                        <?= $contactConfigWidget->render() ?>
                                        <div class="form-buttons">
                                            <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                                <i class="icon-check"></i> Guardar configuración
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Sub-tab: Mensajes recibidos -->
                            <div id="subtab-mensajes" class="tab-pane">
                                <div class="layout padded-container">
                                    <?php if ($submissions->isEmpty()): ?>
                                    <div class="alert alert-info">
                                        <i class="icon-envelope-o"></i>
                                        No hay mensajes recibidos aún.
                                    </div>
                                    <?php else: ?>
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Email</th>
                                                <th>Teléfono</th>
                                                <th>Mensaje</th>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($submissions as $s): ?>
                                            <?php $st = $statusLabels[$s->status] ?? ['label' => $s->status, 'class' => 'default'] ?>
                                            <tr>
                                                <td><?= e($s->name) ?></td>
                                                <td><?= e($s->email) ?></td>
                                                <td><?= e($s->phone) ?></td>
                                                <td style="max-width:300px">
                                                    <span title="<?= e($s->message) ?>">
                                                        <?= e(str($s->message)->limit(80)) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="label label-<?= $st['class'] ?>">
                                                        <?= $st['label'] ?>
                                                    </span>
                                                </td>
                                                <td style="white-space:nowrap">
                                                    <?= $s->created_at?->format('d/m/Y H:i') ?>
                                                </td>
                                            </tr>
                                            <?php endforeach ?>
                                        </tbody>
                                    </table>
                                    <?php endif ?>
                                </div>
                            </div>

                        </div><!-- /.tab-content (sub-tabs) -->
                    </div><!-- /.control-tabs (sub-tabs) -->
                </div><!-- /#tab-contacto -->

            </div><!-- /.tab-content (main) -->
        </div><!-- /.control-tabs (main) -->
    </div>
</div>
