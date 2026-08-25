<?php
/**
 * @var \Illuminate\Support\Collection $archetypes
 * @var string $buttonLabel
 * @var string|null $confirm
 */
?>
<form
    data-request="onGenerateAi"
    data-request-flash
    data-request-loading="#ai-loading"
    data-request-success="aeroAiOnGenerateStarted(data)"
    <?php if ($confirm): ?>data-request-confirm="<?= e($confirm) ?>"<?php endif ?>
>
    <?php if ($archetypes->isNotEmpty()): ?>
    <div style="margin-bottom:10px">
        <label for="ai-archetype-select"><strong>Arquetipo (secuencia de bloques)</strong></label>
        <select name="archetype_handle" id="ai-archetype-select" class="form-control custom-select">
            <option value="" data-description="La IA elige uno al azar entre los disponibles para tu nicho.">Automático</option>
            <?php foreach ($archetypes as $archetype): ?>
            <option value="<?= e($archetype->handle) ?>" data-description="<?= e($archetype->description ?? '') ?>">
                <?= e($archetype->name) ?>
            </option>
            <?php endforeach ?>
        </select>
        <small id="ai-archetype-description" class="text-muted" style="display:block; margin-top:4px">
            La IA elige uno al azar entre los disponibles para tu nicho.
        </small>
    </div>
    <?php endif ?>
    <textarea
        name="ai_prompt"
        class="form-control"
        rows="4"
        placeholder="Ej: Somos una clínica dental con 15 años de experiencia, ofrecemos ortodoncia, implantes, blanqueamiento y urgencias 24h. Tenemos precios accesibles y financiación."
        style="width:100%; margin-bottom:10px"
    ></textarea>
    <div class="form-buttons">
        <button type="submit" id="ai-generate-btn" class="btn btn-warning">
            <i class="icon-magic"></i> <?= e($buttonLabel) ?>
        </button>
        <span id="ai-loading" style="display:none; margin-left:10px">
            <i class="icon-spinner icon-spin"></i> Enviando…
        </span>
    </div>
</form>
<div id="ai-result" style="margin-top:15px"></div>
