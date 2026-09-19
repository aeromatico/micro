<?php
/**
 * @var \Illuminate\Support\Collection $archetypes
 * @var \Aero\Sites\Models\Tenant $tenant
 * @var \Illuminate\Support\Collection $aiConnectors  Connectors habilitados de categoría "ai" (Aero.Connector)
 * @var string $genericBasePrompt  Prompt de arranque para la opción "Sin arquetipo" (del nicho del tenant)
 * @var string $buttonLabel
 * @var string|null $confirm
 * @var string $lastPrompt          Último prompt usado (solo en "Reconstruir"; vacío en el primer diseño)
 * @var string $lastArchetypeHandle Último arquetipo usado, ídem
 * @var int|null $lastConnectorId   Último modelo de IA usado, ídem
 */
$lastPrompt          ??= '';
$lastArchetypeHandle ??= '';
$lastConnectorId     ??= null;
?>
<?php
/**
 * Ojo: esto NO puede ser un <form> — este partial se embebe como campo
 * "ai_panel" dentro del Form widget de la página de inicio (ver
 * ContentEditor::makePageFormWidget), que ya está envuelto en su propio
 * <form data-request="onSaveIndex"> (controllers/contenteditor/index.php).
 * HTML no permite <form> anidados: el navegador descarta la etiqueta
 * interna al parsear y sus campos terminan perteneciendo al form exterior
 * — el botón "Generar" quedaba enviando "Guardar página de inicio" en vez
 * de disparar onGenerateAi, sin ningún error visible (bug real, encontrado
 * con Playwright: cero requests a onGenerateAi al hacer clic). Por eso el
 * envío se dispara a mano por JS (ver _ai_panel_field.php) en vez de con
 * data-request nativo.
 */
?>
<div id="ai-generate-panel" data-confirm="<?= $confirm ? e($confirm) : '' ?>">
    <?php if ($archetypes->isNotEmpty()): ?>
    <div style="margin-bottom:10px">
        <label for="ai-archetype-select"><strong>Arquetipo (secuencia de bloques)</strong></label>
        <select name="archetype_handle" id="ai-archetype-select" class="form-control custom-select">
            <option value="" data-description="La IA elige la secuencia de bloques más afín a tu negocio; tú solo describes el negocio." data-base-prompt="<?= e($genericBasePrompt) ?>" <?= $lastArchetypeHandle === '' ? 'selected' : '' ?>>Sin arquetipo</option>
            <?php foreach ($archetypes as $archetype): ?>
            <option
                value="<?= e($archetype->handle) ?>"
                data-description="<?= e($archetype->description ?? '') ?>"
                data-base-prompt="<?= e($archetype->resolveBasePrompt($tenant->niche_type)) ?>"
                <?= $lastArchetypeHandle === $archetype->handle ? 'selected' : '' ?>
            >
                <?= e($archetype->name) ?>
            </option>
            <?php endforeach ?>
        </select>
        <small id="ai-archetype-description" class="text-muted" style="display:block; margin-top:4px">
            La IA elige la secuencia de bloques más afín a tu negocio; tú solo describes el negocio.
        </small>
    </div>
    <?php endif ?>
    <?php if ($aiConnectors->isNotEmpty()): ?>
    <div style="margin-bottom:10px">
        <label for="ai-connector-select"><strong>Modelo de IA</strong></label>
        <select name="connector_id" id="ai-connector-select" class="form-control custom-select">
            <option value="" <?= !$lastConnectorId ? 'selected' : '' ?>>Automático (proveedor por defecto)</option>
            <?php foreach ($aiConnectors as $connector): ?>
            <option value="<?= (int) $connector->id ?>" <?= $lastConnectorId == $connector->id ? 'selected' : '' ?>>
                <?= e($connector->name) ?>
            </option>
            <?php endforeach ?>
        </select>
        <small class="text-muted" style="display:block; margin-top:4px">
            Todos generan el mismo tipo de landing — elige uno para comparar redacción y estilo entre modelos.
        </small>
    </div>
    <?php endif ?>
    <textarea
        name="ai_prompt"
        id="ai-prompt-textarea"
        class="form-control"
        rows="4"
        placeholder="Describe tu negocio: rubro, años de experiencia, servicios o productos, diferencial y a quién atiendes."
        style="width:100%; margin-bottom:10px"
    ><?= e($lastPrompt !== '' ? $lastPrompt : ($genericBasePrompt ?? '')) ?></textarea>
    <small class="text-muted" style="display:block; margin-top:-6px; margin-bottom:10px">
        Al elegir un arquetipo con prompt base, este campo se autocompleta con un texto de ejemplo — edítalo con los datos reales de tu negocio.
    </small>
    <div class="form-buttons">
        <button type="button" id="ai-generate-btn" class="btn btn-primary">
            <i class="icon-magic"></i> <?= e($buttonLabel) ?>
        </button>
        <span id="ai-loading" style="display:none; margin-left:10px">
            <i class="icon-spinner icon-spin"></i> Enviando…
        </span>
    </div>
</div>
<div id="ai-result" style="margin-top:15px"></div>
