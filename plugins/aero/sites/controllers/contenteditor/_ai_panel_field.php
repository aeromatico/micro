<?php
/**
 * Campo tipo "partial" de $indexPageWidget (ver Page/fields.yaml, campo
 * ai_panel) — vive DENTRO del form widget, inmediatamente después de
 * "Tipo de contenido", para que su propio `trigger` (show solo si
 * content_mode = puck) use el mecanismo nativo de October: la búsqueda del
 * campo disparador solo funciona si ambos viven en el mismo contenedor
 * data-control="formwidget", así que este panel no puede vivir fuera del
 * widget (ver controllers/contenteditor/index.php, que antes lo intentaba
 * con JS propio y quedaba desincronizado del orden real de los campos).
 *
 * $model / $formModel = la Page (indexPage) — inyectados por
 * modules/backend/widgets/form/partials/_field_partial.php. El resto
 * (archetypes, tenant, aiConnectors, lastGeneration) llega por el merge
 * automático de Controller::makePartial() con $this->vars del controller.
 *
 * @var Aero\Sites\Models\Page|null $model
 * @var \Illuminate\Support\Collection $archetypes
 * @var Aero\Sites\Models\Tenant $tenant
 * @var \Illuminate\Support\Collection $aiConnectors
 * @var Aero\Sites\Models\AiGeneration|null $lastGeneration
 */

// Un sitio "ya construido" tiene página + al menos un bloque en el editor
// visual. Se usa para distinguir el primer lanzamiento (CTA grande, siempre
// visible) de una reconstrucción (panel colapsado + confirmación, porque
// reemplaza el diseño y descarta ediciones manuales — SiteGenerator siempre
// regenera la página completa desde cero, no hay modo "actualizar parcial").
$hasExistingDesign = $model && !empty($model->puck_data);

// Prompt de arranque para quien no elige ningún arquetipo — sale del nicho
// del propio tenant (specs/niches/{handle}.yaml vía NicheManager), no de un
// registro de Archetype, para que ya venga afín al rubro del negocio en vez
// de un texto genérico sin relación con el nicho.
$genericBasePrompt = app(\Aero\Sites\Classes\Niches\NicheManager::class)
    ->make($tenant->niche_type)
    ->getBasePrompt();
?>
<?php if (!$hasExistingDesign): ?>
<div id="ai-panel">
    <h4 style="margin-top:0">Genera tu primer diseño con Inteligencia Artificial</h4>
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
<div id="ai-panel">
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
            // "Rehacer con IA" (botón de acceso rápido junto a Guardar)
            // precarga esto y dispara el submit directo, sin que el
            // usuario tenga que reescribir el prompt.
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
        // "Guardar página de inicio" y "Rehacer con IA" quedan bloqueados
        // mientras la IA genera: el editor Puck de abajo sigue mostrando el
        // estado con el que cargó la página (no se entera de lo que la IA
        // va escribiendo en el servidor), así que un click en "Guardar" acá
        // pisaría el resultado recién generado con datos viejos apenas
        // termine — es justamente lo que dejaba la home en blanco y
        // is_placeholder en false sin contenido real. El reload automático
        // de abajo (ver poll()) es la única vía segura para ver el
        // resultado nuevo.
        ['ai-generate-btn', 'save-index-btn', 'ai-redo-btn'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.disabled = isGenerating;
        });

        var notice = document.getElementById('ai-generating-notice');
        if (notice) notice.style.display = isGenerating ? 'flex' : 'none';
    }

    // Contador de "Generando… (Ns)" — arrancado a mano en vez de con un
    // <script> embebido en el HTML inyectado, porque innerHTML nunca
    // ejecuta <script> (limitación del DOM, no de October). pendingTimer
    // en el cierre de arriba para poder cortarlo si un nuevo render de
    // #ai-result lo reemplaza antes de que termine (evita contadores
    // fantasma corriendo en segundo plano).
    var pendingTimer = null;

    function startPendingCounter() {
        if (pendingTimer) clearInterval(pendingTimer);

        var el = document.querySelector('#ai-result [data-ai-pending]');
        if (!el) return;

        var started = parseInt(el.getAttribute('data-started'), 10) * 1000;
        var elapsedEl = el.querySelector('[data-ai-pending-elapsed]');
        var dotsEl = el.querySelector('[data-ai-pending-dots]');
        var frame = 0;

        pendingTimer = setInterval(function () {
            if (!document.body.contains(el)) {
                clearInterval(pendingTimer);
                pendingTimer = null;
                return;
            }
            var elapsedSeconds = Math.floor((Date.now() - started) / 1000);
            if (elapsedEl) elapsedEl.textContent = ' (' + elapsedSeconds + 's)';
            if (dotsEl) dotsEl.textContent = '.'.repeat((frame++ % 3) + 1);
        }, 1000);
    }

    // OJO: la clave de respuesta NO puede llamarse '#ai-result' — así se
    // llamaba antes y el propio parche automático de DOM de October
    // (DomPatcher) entraba en carrera con la respuesta del primer poll y
    // el contenido de #ai-result terminaba vacío siempre (verificado con
    // Playwright inspeccionando ambas respuestas: la de onGenerateAi
    // llegaba con el HTML correcto, pero nunca aparecía en pantalla). Por
    // eso el servidor manda el HTML como dato plano (pendingHtml,
    // resultHtml, errorHtml) y acá se inyecta a mano con innerHTML.
    function setAiResult(html) {
        var el = document.getElementById('ai-result');
        if (el) el.innerHTML = html || '';
    }

    function poll(logId) {
        oc.request('#ai-panel', 'onCheckAiStatus', {
            data: { log_id: logId },
            success: function (data) {
                if (data.status === 'pending' || data.status === 'processing') {
                    setTimeout(function () { poll(logId); }, POLL_INTERVAL_MS);
                } else if (data.status === 'done') {
                    if (pendingTimer) { clearInterval(pendingTimer); pendingTimer = null; }
                    setAiResult(data.resultHtml);
                    // El widget del editor Puck de abajo quedó con los datos
                    // viejos (se renderizó server-side al cargar la página).
                    // Recargamos para que muestre el diseño recién generado;
                    // si no, un "Guardar" posterior pisaría el resultado de la IA.
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    if (pendingTimer) { clearInterval(pendingTimer); pendingTimer = null; }
                    if (data.status === 'failed') setAiResult(data.errorHtml);
                    setGenerating(false);
                }
            },
            error: function () {
                if (pendingTimer) { clearInterval(pendingTimer); pendingTimer = null; }
                setGenerating(false);
            },
        });
    }

    window.aeroAiOnGenerateStarted = function (data) {
        if (!data || !data.aiLogId) return;
        setAiResult(data.pendingHtml);
        startPendingCounter();
        setGenerating(true);
        poll(data.aiLogId);
    };

    // Disparo manual de onGenerateAi: _ai_form.php ya no es un <form> propio
    // (ver el comentario ahí — anidar <form> dentro del <form
    // data-request="onSaveIndex"> de la página de inicio hacía que el
    // navegador descartara la etiqueta interna, y el botón terminaba
    // enviando "Guardar página de inicio" en silencio en vez de generar).
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#ai-generate-btn');
        if (!btn) return;

        var panel = document.getElementById('ai-generate-panel');
        var archetypeSelect = document.getElementById('ai-archetype-select');
        var connectorSelect = document.getElementById('ai-connector-select');
        var promptTextarea = document.getElementById('ai-prompt-textarea');
        var confirmMessage = panel ? panel.getAttribute('data-confirm') : '';

        var options = {
            flash: true,
            loading: '#ai-loading',
            data: {
                archetype_handle: archetypeSelect ? archetypeSelect.value : '',
                connector_id: connectorSelect ? connectorSelect.value : '',
                ai_prompt: promptTextarea ? promptTextarea.value : '',
            },
            success: function (data) {
                window.aeroAiOnGenerateStarted(data);
            },
        };
        if (confirmMessage) options.confirm = confirmMessage;

        oc.request(btn, 'onGenerateAi', options);
    });

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
    // - force=true (el usuario cambió de arquetipo): reemplaza siempre,
    //   para que el campo refleje al instante el prompt realmente
    //   asociado a esa opción.
    // - force=false (carga inicial): sólo autocompleta si el campo viene
    //   vacío, así no pisa el último prompt usado en "Rehacer con IA".
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

    // Delegado en document: "Rehacer con IA" vive en el form principal,
    // fuera de este campo — el panel puede no existir todavía cuando ese
    // botón se clickea la primera vez si el admin nunca lo desplegó.
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#ai-redo-btn')) return;

        var panel = document.getElementById('ai-panel');
        var body = panel ? panel.querySelector('[data-ai-body]') : null;
        if (body) body.style.display = '';
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // El form ya viene precargado con el último prompt/arquetipo usado
        // (ver ContentEditor::index() → $lastGeneration), así que "Rehacer
        // con IA" dispara el submit directo — el usuario solo confirma el
        // diálogo de "esto reemplaza el diseño actual", no tiene que
        // reescribir nada.
        var generateBtn = document.getElementById('ai-generate-btn');
        if (generateBtn) generateBtn.click();
    });
})();
</script>
